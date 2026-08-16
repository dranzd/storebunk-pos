<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\StorebunkPos\Application\Shift\Command\ForceCloseShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

/**
 * ForceCloseShiftHandler
 *
 * Handles the ForceCloseShift command by force-closing the shift under
 * supervisor override.
 */
final class ForceCloseShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository
    ) {
    }

    public function __invoke(ForceCloseShift $command): void
    {
        $shift = $this->shiftRepository->load(ShiftId::fromNative($command->shiftId));
        $shift->forceClose($command->supervisorId, $command->reason);
        $this->shiftRepository->store($shift);
    }
}
