<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\CloseShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;

final class CloseShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository
    ) {
    }

    final public function __invoke(CloseShift $command): void
    {
        $shift = $this->shiftRepository->load($command->shiftId());
        $shift->close($command->declaredClosingCashAmount());
        $this->shiftRepository->store($shift);
    }
}
