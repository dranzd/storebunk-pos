<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * UnassignShift
 *
 * Command to clear a shift's membership, returning it to "open" (no
 * assignee, no fallbacks). The inverse of {@see AssignShift}.
 */
final class UnassignShift extends AbstractCommand
{
    public function __construct(
        public readonly string $shiftId
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.unassign';
    }
}
