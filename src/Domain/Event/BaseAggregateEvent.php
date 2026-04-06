<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Event;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateEventWithPrivateConstructorTrait;

/**
 * BaseAggregateEvent
 *
 * Base class for all POS domain events enforcing the payload-based serialization contract
 * from the common-event-sourcing library.
 *
 * All POS events MUST:
 * 1. Extend this base class
 * 2. Implement getPayload(): array - return complete event data
 * 3. Implement setPayload(array): void - hydrate from payload
 * 4. Use AggregateEventWithPrivateConstructorTrait - enforce private constructor
 *
 * This ensures:
 * - Event serialization works correctly (payload not empty)
 * - Deserialization reconstructs state properly
 * - Generic consumers can access event data via getPayload()
 * - Schema evolution via upcasters becomes possible
 * - Decoupling from specific event class dependencies
 *
 * @see AggregateEventWithPrivateConstructorTrait for constructor enforcement
 * @see AbstractAggregateEvent for base event functionality
 *
 * Usage Example:
 *
 * ```php
 * final class SessionStarted extends BaseAggregateEvent implements DomainEventInterface
 * {
 *     use AggregateEventWithPrivateConstructorTrait;
 *
 *     private SessionId $sessionId;
 *     private ShiftId $shiftId;
 *
 *     final public static function occur(
 *         SessionId $sessionId,
 *         ShiftId $shiftId
 *     ): self {
 *         $instance = new self();
 *         $instance->sessionId = $sessionId;
 *         $instance->shiftId = $shiftId;
 *         return $instance;
 *     }
 *
 *     final public function getPayload(): array
 *     {
 *         return [
 *             'session_id' => $this->sessionId->toString(),
 *             'shift_id' => $this->shiftId->toString(),
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
 *     }
 * }
 * ```
 */
abstract class BaseAggregateEvent extends AbstractAggregateEvent
{
    use AggregateEventWithPrivateConstructorTrait;

    /**
     * Return event data as serializable array.
     *
     * This is the PRIMARY and CANONICAL method for accessing event data.
     * All event fields must be represented here in a format suitable for:
     * - Serialization to event store
     * - Deserialization from event store
     * - Generic consumer access (projections, sagas, etc.)
     * - Upcasting for schema evolution
     *
     * Field naming convention: Use snake_case for all keys.
     * Date format: Use \DateTimeInterface::ATOM (RFC 3339) for all timestamps.
     * Value objects: Use toString() or toNative() for serialization.
     * Null values: Include in array with null value.
     *
     * @return array<string, mixed> Event data with all fields
     *
     * @throws \RuntimeException If properties are not initialized
     *
     * Example return:
     * ```php
     * [
     *     'session_id' => '123e4567-e89b-12d3-a456-426614174000',
     *     'shift_id' => 'shift-789',
     *     'terminal_id' => 'terminal-001',
     *     'started_at' => '2025-04-06T10:00:00+00:00',
     *     'employee_id' => null,  // Optional field
     * ]
     * ```
     */
    abstract public function getPayload(): array;

    /**
     * Hydrate event from serialized payload.
     *
     * Called by the framework when deserializing events from storage.
     * This method MUST reconstruct all object state from the payload array.
     * It's the inverse of getPayload().
     *
     * Implementation requirements:
     * - Handle empty payload (may be called during construction)
     * - Convert payload scalar values to domain objects
     * - Handle optional fields with null-coalescing
     * - Parse dates using DateTimeImmutable
     * - Reconstruct value objects using fromNative/from methods
     *
     * @param array<string, mixed> $payload Serialized event data from storage
     *
     * @return void
     *
     * Example implementation:
     * ```php
     * final protected function setPayload(array $payload): void
     * {
     *     if (empty($payload)) {
     *         return;  // Guard clause - may be called during construction
     *     }
     *
     *     $this->sessionId = SessionId::fromNative($payload['session_id']);
     *     $this->shiftId = ShiftId::fromNative($payload['shift_id']);
     *     $this->terminalId = TerminalId::fromNative($payload['terminal_id']);
     *     $this->startedAt = new \DateTimeImmutable($payload['started_at']);
     *     $this->employeeId = isset($payload['employee_id'])
     *         ? EmployeeId::fromNative($payload['employee_id'])
     *         : null;
     * }
     * ```
     *
     * @see getPayload() for the inverse operation
     */
    abstract protected function setPayload(array $payload): void;

    /**
     * Get event message name (event type identifier).
     *
     * Must be implemented by all subclasses.
     * Example: 'storebunk.pos.session.started'
     *
     * @return string
     */
    abstract public static function expectedMessageName(): string;

    /**
     * Get when the event occurred.
     *
     * Should be implemented by subclasses to return the domain timestamp
     * (not the system timestamp when event was recorded).
     * Example: The moment the session was actually started.
     *
     * @return \DateTimeImmutable
     */
    abstract public function occurredAt(): \DateTimeImmutable;
}
