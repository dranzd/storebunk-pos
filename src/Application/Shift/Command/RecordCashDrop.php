<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class RecordCashDrop extends AbstractCommand
{
    private function __construct(
        private readonly string $shiftId,
        private readonly int $amount,
        private readonly string $currency,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            [
                'shift_id' => $this->shiftId,
                'amount' => [
                    'amount' => $this->amount,
                    'currency' => $this->currency,
                ],
            ]
        );
    }

    final public static function ofAmount(
        string $shiftId,
        int $amount,
        string $currency,
        ?string $commandId = null
    ): self {
        return new self($shiftId, $amount, $currency, $commandId ?? '');
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.record_cash_drop';
    }

    final public function shiftId(): ShiftId
    {
        return ShiftId::fromNative($this->shiftId);
    }

    final public function amount(): Money
    {
        return Money::fromArray(['amount' => $this->amount, 'currency' => $this->currency]);
    }
}
