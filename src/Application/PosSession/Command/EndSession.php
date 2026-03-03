<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class EndSession extends AbstractCommand
{
    private function __construct(
        private readonly string $sessionId
    ) {
        parent::__construct(
            $this->sessionId,
            self::expectedMessageName(),
            ['session_id' => $this->sessionId]
        );
    }

    final public static function withId(string $sessionId): self
    {
        return new self($sessionId);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.end';
    }

    final public function sessionId(): SessionId
    {
        return SessionId::fromNative($this->sessionId);
    }
}
