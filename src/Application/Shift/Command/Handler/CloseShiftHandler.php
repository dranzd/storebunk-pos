<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\ReadModel\PosSessionReadModelInterface;
use Dranzd\StorebunkPos\Application\Shift\Command\CloseShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Service\ShiftClosePolicy;

final class CloseShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly ShiftClosePolicy $shiftClosePolicy,
        private readonly PosSessionReadModelInterface $posSessionReadModel
    ) {
    }

    final public function __invoke(CloseShift $command): void
    {
        $activeSessions = $this->posSessionReadModel->findActiveByShiftId(
            $command->shiftId()->toNative()
        );

        $this->shiftClosePolicy->assertCanClose($command->shiftId(), $activeSessions);

        $shift = $this->shiftRepository->load($command->shiftId());
        $shift->close($command->declaredClosingCashAmount());
        $this->shiftRepository->store($shift);
    }
}
