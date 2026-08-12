<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * StartNewOrderOffline
 *
 * Command to start a new order while the session is offline. Callers that
 * need a deterministic command id for idempotent replay should use
 * withMessageUuid() on the constructed command.
 */
final class StartNewOrderOffline extends AbstractCommand
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
        return 'storebunk.pos.session.start_new_order_offline';
    }
}
