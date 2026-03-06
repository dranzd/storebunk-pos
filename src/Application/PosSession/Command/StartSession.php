<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class StartSession extends AbstractCommand
{
    private function __construct(
        private readonly string $sessionId,
        private readonly string $shiftId,
        private readonly string $terminalId,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            [
                'session_id' => $this->sessionId,
                'shift_id' => $this->shiftId,
                'terminal_id' => $this->terminalId,
            ]
        );
    }

    final public static function onTerminal(
        string $sessionId,
        string $shiftId,
        string $terminalId,
        ?string $commandId = null
    ): self {
        return new self($sessionId, $shiftId, $terminalId, $commandId ?? '');
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.start';
    }

    final public function sessionId(): SessionId
    {
        return SessionId::fromNative($this->sessionId);
    }

    final public function shiftId(): ShiftId
    {
        return ShiftId::fromNative($this->shiftId);
    }

    final public function terminalId(): TerminalId
    {
        return TerminalId::fromNative($this->terminalId);
    }
}
