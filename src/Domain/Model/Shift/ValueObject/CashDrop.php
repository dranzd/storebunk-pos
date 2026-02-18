<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\Domain\ValueObject\ValueObject;

final class CashDrop implements ValueObject
{
    private function __construct(
        private readonly Money $amount,
        private readonly DateTimeImmutable $recordedAt
    ) {
    }

    final public static function record(Money $amount, DateTimeImmutable $recordedAt): self
    {
        return new self($amount, $recordedAt);
    }

    final public function amount(): Money
    {
        return $this->amount;
    }

    final public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    final public function sameValueAs(ValueObject $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->amount->sameValueAs($other->amount)
            && $this->recordedAt == $other->recordedAt;
    }

    final public function toNative(): array
    {
        return [
            'amount' => $this->amount->toArray(),
            'recorded_at' => $this->recordedAt->format(DATE_ATOM),
        ];
    }

    final public static function fromNative(array $native): self
    {
        return new self(
            Money::fromArray($native['amount']),
            new DateTimeImmutable($native['recorded_at'])
        );
    }

    final public function __toString(): string
    {
        return sprintf(
            'CashDrop[%s at %s]',
            $this->amount->__toString(),
            $this->recordedAt->format(DATE_ATOM)
        );
    }
}
