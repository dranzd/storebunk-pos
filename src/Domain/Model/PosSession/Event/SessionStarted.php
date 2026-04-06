<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class SessionStarted extends BaseAggregateEvent implements
    DomainEventInterface
{
    private SessionId $sessionId;
    private ShiftId $shiftId;
    private TerminalId $terminalId;
    private DateTimeImmutable $startedAt;

    final public static function occur(
        SessionId $sessionId,
        ShiftId $shiftId,
        TerminalId $terminalId,
        DateTimeImmutable $startedAt,
    ): self {
        $instance = new self();
        $instance->sessionId = $sessionId;
        $instance->shiftId = $shiftId;
        $instance->terminalId = $terminalId;
        $instance->startedAt = $startedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.session.started";
    }

    final public function getPayload(): array
    {
        return [
            "session_id" => $this->sessionId->toNative(),
            "shift_id" => $this->shiftId->toNative(),
            "terminal_id" => $this->terminalId->toNative(),
            "started_at" => $this->startedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->sessionId = SessionId::fromNative($payload["session_id"]);
        $this->shiftId = ShiftId::fromNative($payload["shift_id"]);
        $this->terminalId = TerminalId::fromNative($payload["terminal_id"]);
        $this->startedAt = new DateTimeImmutable($payload["started_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    final public function getSessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function getShiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }
}
