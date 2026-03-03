<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class CompleteOrder extends AbstractCommand
{
    private function __construct(
        private readonly string $sessionId,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            ['session_id' => $this->sessionId]
        );
    }

    final public static function forSession(
        string $sessionId,
        ?string $commandId = null
    ): self
    {
        return new self($sessionId, $commandId ?? '');
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.complete_order';
    }

    final public function sessionId(): SessionId
    {
        return SessionId::fromNative($this->sessionId);
    }
}
