<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * RecordCashDrop
 *
 * Command to record a cash drop from the shift's drawer.
 */
final class RecordCashDrop extends AbstractCommand
{
    public function __construct(
        public readonly string $shiftId,
        public readonly int $amount,
        public readonly string $currency
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.record_cash_drop';
    }
}
