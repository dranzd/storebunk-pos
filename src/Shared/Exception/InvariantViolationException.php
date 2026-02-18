<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Shared\Exception;

/**
 * Thrown when a business invariant is violated.
 */
class InvariantViolationException extends DomainException
{
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
