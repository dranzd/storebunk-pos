<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class StartSession extends AbstractCommand
{
    private SessionId $sessionId;
    private ShiftId $shiftId;
    private TerminalId $terminalId;

    public function __construct(SessionId $sessionId, ShiftId $shiftId, TerminalId $terminalId)
    {
        $this->sessionId = $sessionId;
        $this->shiftId = $shiftId;
        $this->terminalId = $terminalId;

        parent::__construct(
            $sessionId->toNative(),
            self::expectedMessageName(),
            [
                'session_id' => $sessionId->toNative(),
                'shift_id' => $shiftId->toNative(),
                'terminal_id' => $terminalId->toNative(),
            ]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.start';
    }

    final public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function shiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }
}
