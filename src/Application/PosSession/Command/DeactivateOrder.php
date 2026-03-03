<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class DeactivateOrder extends AbstractCommand
{
    private function __construct(
        private readonly string $sessionId,
        private readonly string $reason
    ) {
        parent::__construct(
            $this->sessionId,
            self::expectedMessageName(),
            [
                'session_id' => $this->sessionId,
                'reason' => $this->reason,
            ]
        );
    }

    final public static function because(string $sessionId, string $reason): self
    {
        return new self($sessionId, $reason);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.deactivate_order';
    }

    final public function sessionId(): SessionId
    {
        return SessionId::fromString($this->sessionId);
    }

    final public function reason(): string
    {
        return $this->reason;
    }
}
