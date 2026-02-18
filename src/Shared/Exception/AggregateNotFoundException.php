<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Shared\Exception;

/**
 * Thrown when an aggregate cannot be found in the event store or repository.
 */
class AggregateNotFoundException extends DomainException
{
    public static function withId(string $aggregateId, string $aggregateType): self
    {
        return new self(
            sprintf('Aggregate of type "%s" with ID "%s" not found', $aggregateType, $aggregateId)
        );
    }
}
