<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Event;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class CashDropRecorded extends BaseAggregateEvent implements DomainEventInterface
{
    private ShiftId $shiftId;
    private Money $amount;
    private DateTimeImmutable $recordedAt;

    final public static function occur(
        ShiftId $shiftId,
        Money $amount,
        DateTimeImmutable $recordedAt
    ): self {
        $instance = new self();
        $instance->shiftId = $shiftId;
        $instance->amount = $amount;
        $instance->recordedAt = $recordedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.shift.cash_drop_recorded";
    }

    final public function getPayload(): array
    {
        return [
            "shift_id" => $this->shiftId->toNative(),
            "amount" => $this->amount->toArray(),
            "recorded_at" => $this->recordedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->shiftId = ShiftId::fromNative($payload["shift_id"]);
        $this->amount = Money::fromArray($payload["amount"]);
        $this->recordedAt = new DateTimeImmutable($payload["recorded_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    final public function getShiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function getAmount(): Money
    {
        return $this->amount;
    }

    final public function getRecordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
