<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\CommandHandler;

use Dranzd\StorebunkPos\Domain\Model\PosSession\Command\CompleteOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;
use Dranzd\StorebunkPos\Domain\Service\OrderingServiceInterface;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

final class CompleteOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly OrderingServiceInterface $orderingService,
        private readonly InventoryServiceInterface $inventoryService
    ) {
    }

    final public function __invoke(CompleteOrder $command): void
    {
        $session = $this->sessionRepository->load($command->sessionId());

        $events = $session->popRecordedEvents();
        $lastEvent = end($events);

        if ($lastEvent instanceof \Dranzd\StorebunkPos\Domain\Model\PosSession\Event\CheckoutInitiated) {
            $orderId = $lastEvent->orderId();

            if (!$this->orderingService->isOrderFullyPaid($orderId)) {
                throw InvariantViolationException::withMessage('Order is not fully paid');
            }
        }

        $session->completeOrder();

        $events = $session->popRecordedEvents();
        $completedEvent = end($events);

        if ($completedEvent instanceof \Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCompleted) {
            $this->inventoryService->deductInventory($completedEvent->orderId());
        }

        $this->sessionRepository->store($session);
    }
}
