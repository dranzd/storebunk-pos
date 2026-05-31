<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\UnassignShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;

final class UnassignShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository
    ) {
    }

    final public function __invoke(UnassignShift $command): void
    {
        $shift = $this->shiftRepository->load($command->shiftId());
        $shift->unassign();
        $this->shiftRepository->store($shift);
    }
}
