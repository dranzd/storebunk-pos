<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Application\Shift\ReadModel\ShiftReadModelInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Service\MultiTerminalEnforcementService;

/**
 * AssignShiftHandler
 *
 * Handles the AssignShift command by assigning the shift to a cashier
 * with optional fallback cashiers, after asserting the assignee does not
 * already operate another open shift.
 */
final class AssignShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly MultiTerminalEnforcementService $multiTerminalEnforcement,
        private readonly ShiftReadModelInterface $shiftReadModel
    ) {
    }

    public function __invoke(AssignShift $command): void
    {
        $this->multiTerminalEnforcement->assertCashierFreeForShift(
            $command->assigneeCashierId,
            $command->shiftId,
            $this->shiftReadModel->openShiftByCashier()
        );

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
