<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

/**
 * A shift's operating membership was cleared, returning it to "open" — no
 * assignee and no fallbacks. Host policy then decides who may start a session.
 *
 * The inverse of {@see ShiftAssigned}. Modeled as its own event (not a nullable
 * ShiftAssigned) so the open ⇄ assigned transitions are explicit in the stream.
 */
final class ShiftUnassigned extends BaseAggregateEvent implements DomainEventInterface
{
    private ShiftId $shiftId;
    private DateTimeImmutable $unassignedAt;

    final public static function occur(
        ShiftId $shiftId,
        DateTimeImmutable $unassignedAt,
    ): self {
        $instance = new self();
        $instance->shiftId = $shiftId;
        $instance->unassignedAt = $unassignedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.shift.unassigned";
    }

    final public function getPayload(): array
    {
        return [
            "shift_id" => $this->shiftId->toNative(),
            "unassigned_at" => $this->unassignedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->shiftId = ShiftId::fromNative($payload["shift_id"]);
        $this->unassignedAt = new DateTimeImmutable($payload["unassigned_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->unassignedAt;
    }

    final public function getShiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function getUnassignedAt(): DateTimeImmutable
    {
        return $this->unassignedAt;
    }
}
