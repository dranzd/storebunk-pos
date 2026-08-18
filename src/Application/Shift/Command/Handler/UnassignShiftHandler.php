<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\UnassignShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;
use Dranzd\StorebunkPos\Shared\Exception\SlotCleanupFailedException;

/**
 * UnassignShiftHandler
 *
 * Handles the UnassignShift command by clearing the shift's membership,
 * returning operation of the shift to its original opener. The opener's slot
 * is claimed before the store and only made final after it, while the
 * outgoing assignee keeps theirs throughout — so an opener who meanwhile
 * operates another open shift is refused, and a failed store leaves the
 * assignee exactly where they were.
 */
final class UnassignShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly ShiftSlotReservationInterface $slotReservation
    ) {
    }

    public function __invoke(UnassignShift $command): void
    {
        $shift = $this->shiftRepository->load(ShiftId::fromNative($command->shiftId));
        // Captured BEFORE the domain call — see AssignShiftHandler.
        $expectedVersion = $shift->getAggregateRootVersion();
        $shift->unassign();

        $openerCashierId = $shift->openedBy()->toNative();
        $this->slotReservation->prepareTransfer($command->shiftId, $openerCashierId);

        try {
            $this->shiftRepository->store($shift, $expectedVersion);
        } catch (\Throwable $failure) {
            try {
                $this->slotReservation->abortTransfer($command->shiftId, $openerCashierId);
            } catch (\Throwable $cleanupFailure) {
                // The original failure is preserved as the cause; the
                // uncertain slot state must not stay silent.
                throw SlotCleanupFailedException::afterFailedCommand(
                    $command->shiftId,
                    $failure,
                    $cleanupFailure
                );
            }
            throw $failure;
        }

        try {
            $this->slotReservation->commitTransfer($command->shiftId, $openerCashierId);
        } catch (\Throwable $cleanupFailure) {
            throw SlotCleanupFailedException::afterCommittedCommand($command->shiftId, $cleanupFailure);
        }
    }
}
