<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject;

use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

enum ShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case ForceClosed = 'force_closed';

    final public function isOpen(): bool
    {
        return $this === self::Open;
    }

    final public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    final public function isForceClosed(): bool
    {
        return $this === self::ForceClosed;
    }

    final public static function fromString(string $status): self
    {
        return self::tryFrom($status)
            ?? throw InvariantViolationException::withMessage(
                sprintf('Invalid shift status: "%s"', $status)
            );
    }
}
