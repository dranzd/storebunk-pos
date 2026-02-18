<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Shared\Exception;

/**
 * Thrown when an optimistic concurrency conflict is detected.
 */
class ConcurrencyException extends DomainException
{
    public static function forAggregate(string $aggregateId, int $expectedVersion, int $actualVersion): self
    {
        return new self(sprintf(
            'Concurrency conflict for aggregate "%s": expected version %d, but found version %d.',
            $aggregateId,
            $expectedVersion,
            $actualVersion
        ));
    }
}
