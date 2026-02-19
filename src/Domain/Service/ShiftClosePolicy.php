<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

final class ShiftClosePolicy
{
    /**
     * Assert that a shift can be closed.
     *
     * @param array<int, string> $activeSessionIds Session IDs that are still active for this shift,
     *                                             sourced from the read model before dispatch.
     */
    final public function assertCanClose(ShiftId $shiftId, array $activeSessionIds): void
    {
        if (!empty($activeSessionIds)) {
            throw InvariantViolationException::withMessage(
                sprintf(
                    'Cannot close shift "%s": %d active POS session(s) still exist',
                    $shiftId->toNative(),
                    count($activeSessionIds)
                )
            );
        }
    }
}
