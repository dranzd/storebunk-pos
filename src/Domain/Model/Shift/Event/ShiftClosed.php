<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Event;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class ShiftClosed extends AbstractAggregateEvent implements DomainEventInterface
{
    private ShiftId $shiftId;
    private Money $declaredClosingCashAmount;
    private Money $expectedCashAmount;
    private Money $varianceAmount;
    private DateTimeImmutable $closedAt;

    /**
     * @param array<string, mixed> $array
     */
    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->shiftId = ShiftId::fromNative($array['payload']['shift_id']);
        $event->declaredClosingCashAmount = Money::fromArray($array['payload']['declared_closing_cash_amount']);
        $event->expectedCashAmount = Money::fromArray($array['payload']['expected_cash_amount']);
        $event->varianceAmount = Money::fromArray($array['payload']['variance_amount']);
        $event->closedAt = new DateTimeImmutable($array['payload']['closed_at']);

        return $event;
    }

    final public static function occur(
        ShiftId $shiftId,
        Money $declaredClosingCashAmount,
        Money $expectedCashAmount,
        Money $varianceAmount,
        DateTimeImmutable $closedAt
    ): self {
        $event = new self();
        $event->shiftId = $shiftId;
        $event->declaredClosingCashAmount = $declaredClosingCashAmount;
        $event->expectedCashAmount = $expectedCashAmount;
        $event->varianceAmount = $varianceAmount;
        $event->closedAt = $closedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.closed';
    }

    final public function toArray(): array
    {
        return [
            'shift_id' => $this->shiftId->toNative(),
            'declared_closing_cash_amount' => $this->declaredClosingCashAmount->toArray(),
            'expected_cash_amount' => $this->expectedCashAmount->toArray(),
            'variance_amount' => $this->varianceAmount->toArray(),
            'closed_at' => $this->closedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->closedAt;
    }

    final public function shiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function declaredClosingCashAmount(): Money
    {
        return $this->declaredClosingCashAmount;
    }

    final public function expectedCashAmount(): Money
    {
        return $this->expectedCashAmount;
    }

    final public function varianceAmount(): Money
    {
        return $this->varianceAmount;
    }

    final public function closedAt(): DateTimeImmutable
    {
        return $this->closedAt;
    }
}
