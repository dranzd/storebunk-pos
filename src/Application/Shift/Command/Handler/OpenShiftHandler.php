<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;
use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use Dranzd\StorebunkPos\Shared\Exception\SlotCleanupFailedException;

/**
 * OpenShiftHandler
 *
 * Handles the OpenShift command by atomically reserving the terminal and
 * cashier slots, then opening a new Shift aggregate. The reservation is the
 * concurrency authority for one-shift-per-terminal / one-shift-per-cashier;
 * a failed store releases the slots again.
 */
final class OpenShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly ShiftSlotReservationInterface $slotReservation
    ) {
    }

    public function __invoke(OpenShift $command): void
    {
        $this->assertShiftDoesNotExist(ShiftId::fromNative($command->shiftId));

        $this->slotReservation->reserveForOpen(
            $command->terminalId,
            $command->cashierId,
            $command->shiftId
        );

        try {
            $shift = Shift::open(
                ShiftId::fromNative($command->shiftId),
                TerminalId::fromNative($command->terminalId),
                BranchId::fromNative($command->branchId),
                CashierId::fromNative($command->cashierId),
                Money::fromArray([
                    'amount' => $command->openingCashAmount,
                    'currency' => $command->currency,
                ])
            );

            // expectedVersion 0 = "this stream does not exist yet". The
            // pre-check above gives the friendly refusal; THIS is what makes
            // it safe, by refusing at the append if a shift appeared (and
            // even opened, closed and released its slots) in between.
            $this->shiftRepository->store($shift, 0);
        } catch (\Throwable $failure) {
            try {
                $this->slotReservation->releaseShift($command->shiftId);
            } catch (\Throwable $cleanupFailure) {
                // The original failure is preserved as the cause; a slot left
                // claimed for a shift that does not exist would otherwise
                // block the terminal with nothing pointing at the reason.
                throw SlotCleanupFailedException::afterFailedCommand(
                    $command->shiftId,
                    $failure,
                    $cleanupFailure
                );
            }
            throw $failure;
        }
    }

    /**
     * A shift id is used once. Opening onto an existing stream would append a
     * second ShiftOpened, and replay keeps whatever the first life set that
     * opening does not overwrite — its assignee, its cash drops — so the
     * reopened shift would name an operator who is free to run another shift
     * elsewhere, and close with the old drops in its expected cash. The slot
     * claim cannot catch this: a closed shift released its slots.
     */
    private function assertShiftDoesNotExist(ShiftId $shiftId): void
    {
        try {
            $this->shiftRepository->load($shiftId);
        } catch (AggregateNotFoundException) {
            return;
        }

        throw InvariantViolationException::withMessage(
            sprintf('Shift "%s" already exists; a shift id cannot be reused', $shiftId->toNative())
        );
    }
}
