<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\UnassignShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;

/**
 * UnassignShiftHandler
 *
 * Handles the UnassignShift command by clearing the shift's membership,
 * returning operation of the shift to its original opener. The cashier slot
 * moves back atomically, so an opener who meanwhile operates another open
 * shift is refused; a failed store moves the slot forward again.
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
        $shift->unassign();

        $openerCashierId = $shift->openedBy()->toNative();
        $previousHolder  = $this->slotReservation->transferCashier(
            $command->shiftId,
            $openerCashierId
        );

        try {
            $this->shiftRepository->store($shift);
        } catch (\Throwable $failure) {
            if ($previousHolder !== null) {
                try {
                    $this->slotReservation->compensateTransfer(
                        $command->shiftId,
                        $previousHolder,
                        $openerCashierId
                    );
                } catch (\Throwable) {
                    // Never mask the original persistence failure.
                }
            }
            throw $failure;
        }
    }
}
