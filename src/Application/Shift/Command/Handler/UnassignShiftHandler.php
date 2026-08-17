<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\UnassignShift;
use Dranzd\StorebunkPos\Application\Shift\ReadModel\ShiftReadModelInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Service\MultiTerminalEnforcementService;

/**
 * UnassignShiftHandler
 *
 * Handles the UnassignShift command by clearing the shift's membership,
 * returning operation of the shift to its original opener — refused when
 * that opener meanwhile operates another open shift.
 */
final class UnassignShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly MultiTerminalEnforcementService $multiTerminalEnforcement,
        private readonly ShiftReadModelInterface $shiftReadModel
    ) {
    }

    public function __invoke(UnassignShift $command): void
    {
        // Unassigning hands the shift back to whoever opened it, so the
        // opener must be free — otherwise they would operate two open shifts.
        $shiftRow = $this->shiftReadModel->getShift($command->shiftId);
        if ($shiftRow !== null && is_string($shiftRow['opened_by'] ?? null)) {
            $this->multiTerminalEnforcement->assertCashierFreeForShift(
                $shiftRow['opened_by'],
                $command->shiftId,
                $this->shiftReadModel->openShiftByCashier()
            );
        }

        $shift = $this->shiftRepository->load(ShiftId::fromNative($command->shiftId));
        $shift->unassign();
        $this->shiftRepository->store($shift);
    }
}
