<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class StartSession extends AbstractCommand
{
    private function __construct(
        private readonly string $sessionId,
        private readonly string $shiftId,
        private readonly string $terminalId,
        private readonly string $cashierId,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            [
                'session_id' => $this->sessionId,
                'shift_id' => $this->shiftId,
                'terminal_id' => $this->terminalId,
                'cashier_id' => $this->cashierId,
            ]
        );
    }

    /**
     * Start a session attributed to the operating cashier (the session's domain
     * operator). The cashier is required — a session is always operated by someone.
     *
     * The host User performing the action travels separately as actor metadata
     * (ActorCapable's `_actor_id`); this `cashierId` is the module-owned operator,
     * a distinct concern from the actor.
     */
    final public static function onTerminalForCashier(
        string $sessionId,
        string $shiftId,
        string $terminalId,
        string $cashierId,
        ?string $commandId = null
    ): self {
        return new self($sessionId, $shiftId, $terminalId, $cashierId, $commandId ?? '');
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

    final public function cashierId(): CashierId
    {
        return CashierId::fromNative($this->cashierId);
    }
}
