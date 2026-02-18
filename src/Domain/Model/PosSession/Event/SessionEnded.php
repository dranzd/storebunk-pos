<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class SessionEnded extends AbstractAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private DateTimeImmutable $endedAt;

    final public static function occur(
        SessionId $sessionId,
        DateTimeImmutable $endedAt
    ): self {
        $event = new self();
        $event->sessionId = $sessionId;
        $event->endedAt = $endedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.ended';
    }

    final public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'ended_at' => $this->endedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->endedAt;
    }

    final public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function endedAt(): DateTimeImmutable
    {
        return $this->endedAt;
    }
}
