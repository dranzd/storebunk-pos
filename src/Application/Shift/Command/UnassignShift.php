<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class UnassignShift extends AbstractCommand
{
    private function __construct(
        private readonly string $shiftId,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            [
                'shift_id' => $this->shiftId,
            ]
        );
    }

    /**
     * Clear a shift's membership, returning it to "open" (no assignee, no
     * fallbacks). The inverse of {@see AssignShift}.
     */
    final public static function shift(
        string $shiftId,
        ?string $commandId = null
    ): self {
        return new self($shiftId, $commandId ?? '');
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.unassign';
    }

    final public function shiftId(): ShiftId
    {
        return ShiftId::fromNative($this->shiftId);
    }
}
