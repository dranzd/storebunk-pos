<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * OpenShift
 *
 * Command to open a new shift for a cashier on a terminal.
 */
final class OpenShift extends AbstractCommand
{
    public function __construct(
        public readonly string $shiftId,
        public readonly string $terminalId,
        public readonly string $branchId,
        public readonly string $cashierId,
        public readonly int $openingCashAmount,
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
        return 'storebunk.pos.shift.open';
    }
}
