<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\CancelOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;
use Dranzd\StorebunkPos\Domain\Service\OrderingServiceInterface;

/**
 * CancelOrderHandler
 *
 * Handles the CancelOrder command by cancelling the session's active order
 * and releasing any inventory reservation.
 */
final class CancelOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly OrderingServiceInterface $orderingService,
        private readonly InventoryServiceInterface $inventoryService
    ) {
    }

    public function __invoke(CancelOrder $command): void
    {
        $session = $this->sessionRepository->load(SessionId::fromNative($command->sessionId));
        $orderId = $session->activeOrderId();

        $session->cancelOrder($command->reason);
        $this->sessionRepository->store($session);

        if ($orderId instanceof OrderId) {
            $this->orderingService->cancelOrder($orderId, $command->reason);
            $this->inventoryService->releaseReservation($orderId);
        }
    }
}
