<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

/**
 * AssignShiftHandler
 *
 * Handles the AssignShift command by assigning the shift to a cashier
 * with optional fallback cashiers.
 */
final class AssignShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository
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
        $this->shiftRepository->store($shift);
    }
}
