<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;
use Dranzd\StorebunkPos\Shared\Exception\SlotCleanupFailedException;

/**
 * AssignShiftHandler
 *
 * Handles the AssignShift command by assigning the shift to a cashier with
 * optional fallback cashiers. The assignee's cashier slot is claimed before
 * the store and only made final after it, while the outgoing operator keeps
 * their slot throughout — so a cashier already operating another open shift
 * is refused, and a failed store leaves the previous operator exactly where
 * they were.
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
        // Captured BEFORE the domain call: storing against the version we
        // read is what makes a concurrent change to this shift lose, rather
        // than quietly landing on top of it.
        $expectedVersion = $shift->getAggregateRootVersion();
        $shift->assign(
            CashierId::fromNative($command->assigneeCashierId),
            array_map(
                static fn (string $id): CashierId => CashierId::fromNative($id),
                array_values($command->fallbackCashierIds)
            )
        );

        $this->slotReservation->prepareTransfer(
            $command->shiftId,
            $command->assigneeCashierId
        );

        try {
            $this->shiftRepository->store($shift, $expectedVersion);
        } catch (\Throwable $failure) {
            try {
                $this->slotReservation->abortTransfer(
                    $command->shiftId,
                    $command->assigneeCashierId
                );
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
            $this->slotReservation->commitTransfer(
                $command->shiftId,
                $command->assigneeCashierId
            );
        } catch (\Throwable $cleanupFailure) {
            throw SlotCleanupFailedException::afterCommittedCommand($command->shiftId, $cleanupFailure);
        }
    }
}
