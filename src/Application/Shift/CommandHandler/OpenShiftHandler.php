<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\CommandHandler;

use Dranzd\StorebunkPos\Domain\Model\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;

final class OpenShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository
    ) {
    }

    final public function __invoke(OpenShift $command): void
    {
        $shift = Shift::open(
            $command->shiftId(),
            $command->terminalId(),
            $command->branchId(),
            $command->cashierId(),
            $command->openingCashAmount()
        );

        $this->shiftRepository->store($shift);
    }
}
