<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class RecordCashDrop extends AbstractCommand
{
    private ShiftId $shiftId;
    private Money $amount;

    public function __construct(ShiftId $shiftId, Money $amount)
    {
        $this->shiftId = $shiftId;
        $this->amount = $amount;

        parent::__construct(
            $shiftId->toNative(),
            self::expectedMessageName(),
            ['shift_id' => $shiftId->toNative(), 'amount' => $amount->toArray()]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.record_cash_drop';
    }

    final public function shiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function amount(): Money
    {
        return $this->amount;
    }
}
