<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * StartSession
 *
 * Command to start a POS session attributed to the operating cashier (the
 * session's domain operator). The cashier is required — a session is always
 * operated by someone. The host User performing the action travels separately
 * as actor metadata (ActorCapable's `_actor_id`); `cashierId` is the
 * module-owned operator, a distinct concern from the actor.
 */
final class StartSession extends AbstractCommand
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $shiftId,
        public readonly string $terminalId,
        public readonly string $cashierId
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.start';
    }
}
