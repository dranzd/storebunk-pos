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
    /**
     * @param array<string, mixed> $context Opaque context forwarded verbatim
     *        to the Ordering BC when the draft order is created. POS does not
     *        interpret it — keys and values are consumer/Ordering vocabulary
     *        (see ADR-006).
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $orderId,
        public readonly array $context = []
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
