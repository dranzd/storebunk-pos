<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Event;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;

/**
 * Base class for all POS domain events.
 *
 * Enforces the payload-based serialization contract from common-event-sourcing library.
 *
 * All POS events must:
 * 1. Extend this class
 * 2. Use AggregateEventWithPrivateConstructorTrait (on the concrete event, not here)
 * 3. Implement getPayload(): array - Returns complete event data
 * 4. Implement setPayload(array): void - Hydrates object from payload
 *
 * This pattern ensures:
 * ✅ Events serialize/deserialize correctly
 * ✅ Generic consumers can access event data without instanceof checks
 * ✅ Schema evolution via upcasters is possible
 * ✅ Consistency across all POS events
 *
 * Example Implementation:
 *
 * ```php
 * use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateEventWithPrivateConstructorTrait;
 *
 * final class SessionStarted extends BaseAggregateEvent implements DomainEventInterface
 * {
 *     use AggregateEventWithPrivateConstructorTrait;
 *
 *     private SessionId $sessionId;
 *     private ShiftId $shiftId;
 *     private DateTimeImmutable $startedAt;
 *
 *     final public static function occur(
 *         SessionId $sessionId,
 *         ShiftId $shiftId,
 *         DateTimeImmutable $startedAt
 *     ): self {
 *         $instance = new self();
 *         $instance->sessionId = $sessionId;
 *         $instance->shiftId = $shiftId;
 *         $instance->startedAt = $startedAt;
 *         return $instance;
 *     }
 *
 *     final public static function expectedMessageName(): string
 *     {
 *         return 'storebunk.pos.session.started';
 *     }
 *
 *     final public function getPayload(): array
 *     {
 *         return [
 *             'session_id' => $this->sessionId->toNative(),
 *             'shift_id' => $this->shiftId->toNative(),
 *             'started_at' => $this->startedAt->format(\DateTimeInterface::ATOM),
 *         ];
 *     }
 *
 *     final protected function setPayload(array $payload): void
 *     {
 *         if (empty($payload)) {
 *             return;
 *         }
 *         $this->sessionId = SessionId::fromNative($payload['session_id']);
 *         $this->shiftId = ShiftId::fromNative($payload['shift_id']);
 *         $this->startedAt = new DateTimeImmutable($payload['started_at']);
 *     }
 *
 *     final public function occurredAt(): DateTimeImmutable
 *     {
 *         return $this->startedAt;
 *     }
 *
 *     final public function getSessionId(): SessionId { return $this->sessionId; }
 *     final public function getShiftId(): ShiftId { return $this->shiftId; }
 *     final public function getStartedAt(): DateTimeImmutable { return $this->startedAt; }
 * }
 * ```
 *
 * Key Implementation Notes:
 *
 * 1. getPayload() must return array with ALL event fields:
 *    - Use snake_case for keys
 *    - Use DateTimeInterface::ATOM for dates
 *    - Use ::toNative() for value objects
 *    - Include optional fields as null
 *
 * 2. setPayload() must hydrate from payload:
 *    - Start with guard clause: if (empty($payload)) return;
 *    - Reconstruct all properties from payload keys
 *    - Use ::fromNative() for value objects
 *    - Use DateTimeImmutable for dates
 *    - Handle optional fields with null coalescing
 *
 * 3. Keep all existing public getters for type-safe access
 *
 * 4. Do NOT implement toArray() or fromArray() - parent handles these via getPayload/setPayload
 *
 * 5. occurredAt() should return the domain timestamp (when event actually occurred, not when recorded)
 *
 * 6. expectedMessageName() returns event type identifier (e.g., 'storebunk.pos.session.started')
 *
 * @see AbstractAggregateEvent Base class with event sourcing functionality
 * @see AggregateEventWithPrivateConstructorTrait Use on concrete event classes to enforce private constructors
 */
abstract class BaseAggregateEvent extends AbstractAggregateEvent
{
    /**
     * Return event data as serializable array.
     *
     * This is the canonical source of event state for serialization, deserialization,
     * generic consumer access, and schema evolution.
     *
     * Subclasses MUST override this method to return all event data.
     *
     * @return array<string, mixed> Complete event state
     */
    public function getPayload(): array
    {
        return [];
    }

    /**
     * Hydrate event from serialized payload.
     *
     * Called by framework when deserializing events from storage.
     * Reconstructs all object properties from the payload array.
     *
     * Subclasses MUST override this method to hydrate their properties.
     *
     * @param array<string, mixed> $payload
     */
    protected function setPayload(array $payload): void
    {
        // Subclasses must override
    }
}
