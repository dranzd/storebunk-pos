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

            $this->shiftRepository->store($shift);
        } catch (\Throwable $failure) {
            $this->slotReservation->releaseShift($command->shiftId);
            throw $failure;
        }
    }
}
