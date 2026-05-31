<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;

final class AssignShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository
    ) {
    }

    final public function __invoke(AssignShift $command): void
    {
        $shift = $this->shiftRepository->load($command->shiftId());
        $shift->assign($command->assignee(), $command->fallbackCashiers());
        $this->shiftRepository->store($shift);
    }
}
