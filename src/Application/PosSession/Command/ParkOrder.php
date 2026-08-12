<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * ParkOrder
 *
 * Command to park the active order in a session.
 */
final class ParkOrder extends AbstractCommand
{
    public function __construct(
        public readonly string $sessionId
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.park_order';
    }
}
