<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class CloseShift extends AbstractCommand
{
    private function __construct(
        private readonly string $shiftId,
        private readonly int $declaredClosingCashAmount,
        private readonly string $currency
    ) {
        parent::__construct(
            $this->shiftId,
            self::expectedMessageName(),
            [
                'shift_id' => $this->shiftId,
                'declared_closing_cash_amount' => [
                    'amount' => $this->declaredClosingCashAmount,
                    'currency' => $this->currency,
                ],
            ]
        );
    }

    final public static function withCashAmount(string $shiftId, int $declaredClosingCashAmount, string $currency): self
    {
        return new self($shiftId, $declaredClosingCashAmount, $currency);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.close';
    }

    final public function shiftId(): ShiftId
    {
        return ShiftId::fromString($this->shiftId);
    }

    final public function declaredClosingCashAmount(): Money
    {
        return Money::fromScalar($this->declaredClosingCashAmount, $this->currency);
    }
}
