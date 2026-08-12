<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * SyncOrderOnline
 *
 * Command to sync an offline-started order once the session is back online.
 * Callers that need a deterministic command id for idempotent replay should
 * use withMessageUuid() on the constructed command.
 */
final class SyncOrderOnline extends AbstractCommand
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $orderId,
        public readonly string $branchId,
        public readonly ?string $customerId = null
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.sync_order_online';
    }
}
