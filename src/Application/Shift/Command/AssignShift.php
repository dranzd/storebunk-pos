<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * AssignShift
 *
 * Command to assign the shift to an operating cashier, with an optional set
 * of fallback cashiers (≤3) who may operate it when the assignee is out.
 * Re-issuing this replaces the membership without re-opening the shift.
 */
final class AssignShift extends AbstractCommand
{
    /**
     * @param string[] $fallbackCashierIds
     */
    public function __construct(
        public readonly string $shiftId,
        public readonly string $assigneeCashierId,
        public readonly array $fallbackCashierIds = []
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.assign';
    }
}
