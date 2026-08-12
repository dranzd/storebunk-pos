<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\UnassignShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

/**
 * UnassignShiftHandler
 *
 * Handles the UnassignShift command by clearing the shift's membership.
 */
final class UnassignShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository
    ) {
    }

    public function __invoke(UnassignShift $command): void
    {
        $shift = $this->shiftRepository->load(ShiftId::fromNative($command->shiftId));
        $shift->unassign();
        $this->shiftRepository->store($shift);
    }
}
