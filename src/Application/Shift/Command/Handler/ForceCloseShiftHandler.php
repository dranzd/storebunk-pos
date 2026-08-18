<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\ForceCloseShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;
use Dranzd\StorebunkPos\Shared\Exception\SlotCleanupFailedException;

/**
 * ForceCloseShiftHandler
 *
 * Handles the ForceCloseShift command by force-closing the shift under
 * supervisor override.
 */
final class ForceCloseShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly ShiftSlotReservationInterface $slotReservation
    ) {
    }

    public function __invoke(ForceCloseShift $command): void
    {
        $shift = $this->shiftRepository->load(ShiftId::fromNative($command->shiftId));
        $expectedVersion = $shift->getAggregateRootVersion();
        $shift->forceClose($command->supervisorId, $command->reason);
        $this->shiftRepository->store($shift, $expectedVersion);

        try {
            $this->slotReservation->releaseShift($command->shiftId);
        } catch (\Throwable $cleanupFailure) {
            // The shift IS force-closed; the leftover slots are the part that
            // still needs an operator's attention.
            throw SlotCleanupFailedException::afterCommittedCommand($command->shiftId, $cleanupFailure);
        }
    }
}
