<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Event;

/**
 * DomainEventInterface
 *
 * Marker interface for all domain events in the POS bounded context.
 * All POS aggregate events should extend AbstractAggregateEvent from
 * dranzd/common-event-sourcing and implement this interface.
 *
 * This allows type-hinting and filtering POS-specific events while
 * leveraging the common event sourcing infrastructure.
 *
 * @see \Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent
 */
interface DomainEventInterface
{
    /**
     * Get the expected message name for this event.
     *
     * Used for event serialization and routing.
     */
    public static function expectedMessageName(): string;

    /**
     * Convert event to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Get when the event occurred.
     */
    public function occurredAt(): \DateTimeImmutable;
}
