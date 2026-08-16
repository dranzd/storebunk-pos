<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * DeactivateOrder
 *
 * Command to deactivate the active order in a session.
 */
final class DeactivateOrder extends AbstractCommand
{
    public function __construct(
        public readonly string $sessionId,
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
        return 'storebunk.pos.session.deactivate_order';
    }
}
