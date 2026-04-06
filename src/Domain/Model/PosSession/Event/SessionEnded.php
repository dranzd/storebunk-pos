<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class SessionEnded extends BaseAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private DateTimeImmutable $endedAt;

    final public static function occur(
        SessionId $sessionId,
        DateTimeImmutable $endedAt
    ): self {
        $instance = new self();
        $instance->sessionId = $sessionId;
        $instance->endedAt = $endedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.ended';
    }

    final public function getPayload(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'ended_at' => $this->endedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->sessionId = SessionId::fromNative($payload['session_id']);
        $this->endedAt = new DateTimeImmutable($payload['ended_at']);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->endedAt;
    }

    final public function getSessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function getEndedAt(): DateTimeImmutable
    {
        return $this->endedAt;
    }
}
