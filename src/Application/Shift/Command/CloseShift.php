<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class CloseShift extends AbstractCommand
{
    private ShiftId $shiftId;
    private Money $declaredClosingCashAmount;

    public function __construct(
        ShiftId $shiftId,
        Money $declaredClosingCashAmount
    ) {
        $this->shiftId = $shiftId;
        $this->declaredClosingCashAmount = $declaredClosingCashAmount;

        parent::__construct(
            $shiftId->toNative(),
            self::expectedMessageName(),
            [
                'shift_id' => $shiftId->toNative(),
                'declared_closing_cash_amount' => $declaredClosingCashAmount->toArray(),
            ]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.close';
    }

    final public function shiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function declaredClosingCashAmount(): Money
    {
        return $this->declaredClosingCashAmount;
    }
}
