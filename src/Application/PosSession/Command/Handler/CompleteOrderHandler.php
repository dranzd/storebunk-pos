<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\CompleteOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
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
        $orderId = $session->activeOrderId();

        if ($orderId instanceof OrderId && !$this->orderingService->isOrderFullyPaid($orderId)) {
            throw InvariantViolationException::withMessage('Order is not fully paid');
        }

        $session->completeOrder();
        $this->sessionRepository->store($session);

        if ($orderId instanceof OrderId) {
            $this->inventoryService->fulfillOrderReservation($orderId);
        }
    }
}
