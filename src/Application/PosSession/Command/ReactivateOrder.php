<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * ReactivateOrder
 *
 * Command to reactivate a previously deactivated order in a session.
 */
final class ReactivateOrder extends AbstractCommand
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $orderId
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.reactivate_order';
    }
}
