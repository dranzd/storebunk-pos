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
        private readonly string $currency,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
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

    final public static function withCashAmount(
        string $shiftId,
        int $declaredClosingCashAmount,
        string $currency,
        ?string $commandId = null
    ): self
    {
        return new self($shiftId, $declaredClosingCashAmount, $currency, $commandId ?? '');
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.close';
    }

    final public function shiftId(): ShiftId
    {
        return ShiftId::fromNative($this->shiftId);
    }

    final public function declaredClosingCashAmount(): Money
    {
        return Money::fromArray(['amount' => $this->declaredClosingCashAmount, 'currency' => $this->currency]);
    }
}
