<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Event;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class CashDropRecorded extends AbstractAggregateEvent implements DomainEventInterface
{
    private ShiftId $shiftId;
    private Money $amount;
    private DateTimeImmutable $recordedAt;

    /**
     * @param array<string, mixed> $array
     */
    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->shiftId = ShiftId::fromNative($array['payload']['shift_id']);
        $event->amount = Money::fromArray($array['payload']['amount']);
        $event->recordedAt = new DateTimeImmutable($array['payload']['recorded_at']);

        return $event;
    }

    final public static function occur(
        ShiftId $shiftId,
        Money $amount,
        DateTimeImmutable $recordedAt
    ): self {
        $event = new self();
        $event->shiftId = $shiftId;
        $event->amount = $amount;
        $event->recordedAt = $recordedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.cash_drop_recorded';
    }

    final public function toArray(): array
    {
        return [
            'shift_id' => $this->shiftId->toNative(),
            'amount' => $this->amount->toArray(),
            'recorded_at' => $this->recordedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    final public function shiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function amount(): Money
    {
        return $this->amount;
    }

    final public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
