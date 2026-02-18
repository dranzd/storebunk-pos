<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\CommandHandler;

use Dranzd\StorebunkPos\Domain\Model\PosSession\Command\CancelOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;
use Dranzd\StorebunkPos\Domain\Service\OrderingServiceInterface;

final class CancelOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly OrderingServiceInterface $orderingService,
        private readonly InventoryServiceInterface $inventoryService
    ) {
    }

    final public function __invoke(CancelOrder $command): void
    {
        $session = $this->sessionRepository->load($command->sessionId());
        $session->cancelOrder($command->reason());

        $events = $session->popRecordedEvents();
        $cancelledEvent = end($events);

        if ($cancelledEvent instanceof \Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCancelledViaPOS) {
            $orderId = $cancelledEvent->orderId();
            $this->orderingService->cancelOrder($orderId, $cancelledEvent->reason());
            $this->inventoryService->releaseReservation($orderId);
        }

        $this->sessionRepository->store($session);
    }
}
