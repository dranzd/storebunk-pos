<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class ShiftForceClosed extends AbstractAggregateEvent implements DomainEventInterface
{
    private ShiftId $shiftId;
    private string $supervisorId;
    private string $reason;
    private DateTimeImmutable $forceClosedAt;

    /**
     * @param array<string, mixed> $array
     */
    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->shiftId = ShiftId::fromNative($array['payload']['shift_id']);
        $event->supervisorId = $array['payload']['supervisor_id'];
        $event->reason = $array['payload']['reason'];
        $event->forceClosedAt = new DateTimeImmutable($array['payload']['force_closed_at']);

        return $event;
    }

    final public static function occur(
        ShiftId $shiftId,
        string $supervisorId,
        string $reason,
        DateTimeImmutable $forceClosedAt
    ): self {
        $event = new self();
        $event->shiftId = $shiftId;
        $event->supervisorId = $supervisorId;
        $event->reason = $reason;
        $event->forceClosedAt = $forceClosedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.force_closed';
    }

    final public function toArray(): array
    {
        return [
            'shift_id' => $this->shiftId->toNative(),
            'supervisor_id' => $this->supervisorId,
            'reason' => $this->reason,
            'force_closed_at' => $this->forceClosedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->forceClosedAt;
    }

    final public function shiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function supervisorId(): string
    {
        return $this->supervisorId;
    }

    final public function reason(): string
    {
        return $this->reason;
    }

    final public function forceClosedAt(): DateTimeImmutable
    {
        return $this->forceClosedAt;
    }
}
