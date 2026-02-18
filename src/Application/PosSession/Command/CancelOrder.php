<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class CancelOrder extends AbstractCommand
{
    private SessionId $sessionId;
    private string $reason;

    public function __construct(SessionId $sessionId, string $reason)
    {
        $this->sessionId = $sessionId;
        $this->reason = $reason;

        parent::__construct(
            $sessionId->toNative(),
            self::expectedMessageName(),
            ['session_id' => $sessionId->toNative(), 'reason' => $reason]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.cancel_order';
    }

    final public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function reason(): string
    {
        return $this->reason;
    }
}
