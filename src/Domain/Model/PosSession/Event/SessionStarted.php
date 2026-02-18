<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class SessionStarted extends AbstractAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private ShiftId $shiftId;
    private TerminalId $terminalId;
    private DateTimeImmutable $startedAt;

    final public static function occur(
        SessionId $sessionId,
        ShiftId $shiftId,
        TerminalId $terminalId,
        DateTimeImmutable $startedAt
    ): self {
        $event = new self();
        $event->sessionId = $sessionId;
        $event->shiftId = $shiftId;
        $event->terminalId = $terminalId;
        $event->startedAt = $startedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.started';
    }

    final public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'shift_id' => $this->shiftId->toNative(),
            'terminal_id' => $this->terminalId->toNative(),
            'started_at' => $this->startedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->startedAt;
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

    final public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }
}
