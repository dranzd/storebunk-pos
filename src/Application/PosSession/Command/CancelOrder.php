<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class CancelOrder extends AbstractCommand
{
    private function __construct(
        private readonly string $sessionId,
        private readonly string $reason,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            [
                'session_id' => $this->sessionId,
                'reason' => $this->reason,
            ]
        );
    }

    final public static function because(
        string $sessionId,
        string $reason,
        ?string $commandId = null
    ): self {
        return new self($sessionId, $reason, $commandId ?? '');
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.cancel_order';
    }

    final public function sessionId(): SessionId
    {
        return SessionId::fromNative($this->sessionId);
    }

    final public function reason(): string
    {
        return $this->reason;
    }
}
