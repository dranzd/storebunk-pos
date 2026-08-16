<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * ResumeOrder
 *
 * Command to resume a parked order in a session.
 */
final class ResumeOrder extends AbstractCommand
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
        return 'storebunk.pos.session.resume_order';
    }
}
