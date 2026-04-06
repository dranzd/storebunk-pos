<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Event;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class ShiftClosed extends BaseAggregateEvent implements DomainEventInterface
{
    private ShiftId $shiftId;
    private Money $closingCashAmount;
    private DateTimeImmutable $closedAt;

    final public static function occur(
        ShiftId $shiftId,
        Money $closingCashAmount,
        DateTimeImmutable $closedAt
    ): self {
        $instance = new self();
        $instance->shiftId = $shiftId;
        $instance->closingCashAmount = $closingCashAmount;
        $instance->closedAt = $closedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.shift.closed";
    }

    final public function getPayload(): array
    {
        return [
            "shift_id" => $this->shiftId->toNative(),
            "closing_cash_amount" => $this->closingCashAmount->toArray(),
            "closed_at" => $this->closedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->shiftId = ShiftId::fromNative($payload["shift_id"]);
        $this->closingCashAmount = Money::fromArray($payload["closing_cash_amount"]);
        $this->closedAt = new DateTimeImmutable($payload["closed_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->closedAt;
    }

    final public function getShiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function getClosingCashAmount(): Money
    {
        return $this->closingCashAmount;
    }

    final public function getClosedAt(): DateTimeImmutable
    {
        return $this->closedAt;
    }
}
