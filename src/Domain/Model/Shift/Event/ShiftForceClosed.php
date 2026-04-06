<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class ShiftForceClosed extends BaseAggregateEvent implements DomainEventInterface
{
    private ShiftId $shiftId;
    private string $reason;
    private DateTimeImmutable $forceClosedAt;

    final public static function occur(
        ShiftId $shiftId,
        string $reason,
        DateTimeImmutable $forceClosedAt
    ): self {
        $instance = new self();
        $instance->shiftId = $shiftId;
        $instance->reason = $reason;
        $instance->forceClosedAt = $forceClosedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.shift.force_closed";
    }

    final public function getPayload(): array
    {
        return [
            "shift_id" => $this->shiftId->toNative(),
            "reason" => $this->reason,
            "force_closed_at" => $this->forceClosedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->shiftId = ShiftId::fromNative($payload["shift_id"]);
        $this->reason = $payload["reason"];
        $this->forceClosedAt = new DateTimeImmutable($payload["force_closed_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->forceClosedAt;
    }

    final public function getShiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function getReason(): string
    {
        return $this->reason;
    }

    final public function getForceClosedAt(): DateTimeImmutable
    {
        return $this->forceClosedAt;
    }
}
