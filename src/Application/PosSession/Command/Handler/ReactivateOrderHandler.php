<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\ReactivateOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

final class ReactivateOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly InventoryServiceInterface $inventoryService
    ) {
    }

    final public function __invoke(ReactivateOrder $command): void
    {
        $session = $this->sessionRepository->load($command->sessionId());

        $canReReserve = $this->inventoryService->attemptReReservation($command->orderId());

        if (!$canReReserve) {
            throw InvariantViolationException::withMessage(
                'Cannot reactivate order: insufficient inventory for re-reservation'
            );
        }

        $session->reactivateOrder($command->orderId());

        $this->sessionRepository->store($session);
    }
}
