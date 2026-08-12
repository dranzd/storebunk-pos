<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * ForceCloseShift
 *
 * Command to force-close a shift by supervisor override with a reason.
 */
final class ForceCloseShift extends AbstractCommand
{
    public function __construct(
        public readonly string $shiftId,
        public readonly string $supervisorId,
        public readonly string $reason
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.force_close';
    }
}
