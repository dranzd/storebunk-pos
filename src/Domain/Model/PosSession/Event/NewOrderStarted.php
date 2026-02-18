<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class NewOrderStarted extends AbstractAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;
    private DateTimeImmutable $startedAt;

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        DateTimeImmutable $startedAt
    ): self {
        $event = new self();
        $event->sessionId = $sessionId;
        $event->orderId = $orderId;
        $event->startedAt = $startedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.new_order_started';
    }

    final public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id' => $this->orderId->toNative(),
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

    final public function orderId(): OrderId
    {
        return $this->orderId;
    }

    final public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }
}
