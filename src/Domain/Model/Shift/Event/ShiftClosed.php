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
    private Money $declaredClosingCashAmount;
    private Money $expectedCashAmount;
    private Money $varianceAmount;
    private DateTimeImmutable $closedAt;

    final public static function occur(
        ShiftId $shiftId,
        Money $declaredClosingCashAmount,
        Money $expectedCashAmount,
        Money $varianceAmount,
        DateTimeImmutable $closedAt,
    ): self {
        $instance = new self();
        $instance->shiftId = $shiftId;
        $instance->declaredClosingCashAmount = $declaredClosingCashAmount;
        $instance->expectedCashAmount = $expectedCashAmount;
        $instance->varianceAmount = $varianceAmount;
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
            "declared_closing_cash_amount" => $this->declaredClosingCashAmount->toArray(),
            "expected_cash_amount" => $this->expectedCashAmount->toArray(),
            "variance_amount" => $this->varianceAmount->toArray(),
            "closed_at" => $this->closedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->shiftId = ShiftId::fromNative($payload["shift_id"]);
        $this->declaredClosingCashAmount = Money::fromArray($payload["declared_closing_cash_amount"]);
        $this->expectedCashAmount = Money::fromArray($payload["expected_cash_amount"]);
        $this->varianceAmount = Money::fromArray($payload["variance_amount"]);
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

    final public function getDeclaredClosingCashAmount(): Money
    {
        return $this->declaredClosingCashAmount;
    }

    final public function getExpectedCashAmount(): Money
    {
        return $this->expectedCashAmount;
    }

    final public function getVarianceAmount(): Money
    {
        return $this->varianceAmount;
    }

    final public function getClosedAt(): DateTimeImmutable
    {
        return $this->closedAt;
    }
}
