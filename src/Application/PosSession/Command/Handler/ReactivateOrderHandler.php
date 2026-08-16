<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\ReactivateOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

/**
 * ReactivateOrderHandler
 *
 * Handles the ReactivateOrder command by re-reserving inventory and
 * reactivating the deactivated order in the session.
 */
final class ReactivateOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly InventoryServiceInterface $inventoryService
    ) {
    }

    public function __invoke(ReactivateOrder $command): void
    {
        $session = $this->sessionRepository->load(SessionId::fromNative($command->sessionId));
        $orderId = OrderId::fromNative($command->orderId);

        $canReReserve = $this->inventoryService->attemptReReservation($orderId);

        if (!$canReReserve) {
            throw InvariantViolationException::withMessage(
                'Cannot reactivate order: insufficient inventory for re-reservation'
            );
        }

        $session->reactivateOrder($orderId);

        $this->sessionRepository->store($session);
    }
}
