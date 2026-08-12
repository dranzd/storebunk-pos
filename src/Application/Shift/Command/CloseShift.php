<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * CloseShift
 *
 * Command to close a shift with the declared closing cash amount.
 */
final class CloseShift extends AbstractCommand
{
    public function __construct(
        public readonly string $shiftId,
        public readonly int $declaredClosingCashAmount,
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
        return 'storebunk.pos.shift.close';
    }
}
