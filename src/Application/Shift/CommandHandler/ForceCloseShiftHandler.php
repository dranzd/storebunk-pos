<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\CommandHandler;

use Dranzd\StorebunkPos\Domain\Model\Shift\Command\ForceCloseShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;

final class ForceCloseShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository
    ) {
    }

    final public function __invoke(ForceCloseShift $command): void
    {
        $shift = $this->shiftRepository->load($command->shiftId());
        $shift->forceClose($command->supervisorId(), $command->reason());
        $this->shiftRepository->store($shift);
    }
}
