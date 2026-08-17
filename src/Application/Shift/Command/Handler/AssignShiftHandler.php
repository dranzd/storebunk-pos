<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;

/**
 * AssignShiftHandler
 *
 * Handles the AssignShift command by assigning the shift to a cashier with
 * optional fallback cashiers. The shift's cashier slot moves atomically to
 * the assignee, so a cashier already operating another open shift is
 * refused; a failed store moves the slot back.
 */
final class AssignShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly ShiftSlotReservationInterface $slotReservation
    ) {
    }

    public function __invoke(AssignShift $command): void
    {
        $shift = $this->shiftRepository->load(ShiftId::fromNative($command->shiftId));
        $shift->assign(
            CashierId::fromNative($command->assigneeCashierId),
            array_map(
                static fn (string $id): CashierId => CashierId::fromNative($id),
                array_values($command->fallbackCashierIds)
            )
        );

        $previousHolder = $this->slotReservation->transferCashier(
            $command->shiftId,
            $command->assigneeCashierId
        );

        try {
            $this->shiftRepository->store($shift);
        } catch (\Throwable $failure) {
            if ($previousHolder !== null) {
                try {
                    $this->slotReservation->compensateTransfer(
                        $command->shiftId,
                        $previousHolder,
                        $command->assigneeCashierId
                    );
                } catch (\Throwable) {
                    // Never mask the original persistence failure.
                }
            }
            throw $failure;
        }
    }
}
