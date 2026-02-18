<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\CommandHandler;

use Dranzd\StorebunkPos\Domain\Model\Shift\Command\RecordCashDrop;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;

final class RecordCashDropHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository
    ) {
    }

    final public function __invoke(RecordCashDrop $command): void
    {
        $shift = $this->shiftRepository->load($command->shiftId());
        $shift->recordCashDrop($command->amount());
        $this->shiftRepository->store($shift);
    }
}
