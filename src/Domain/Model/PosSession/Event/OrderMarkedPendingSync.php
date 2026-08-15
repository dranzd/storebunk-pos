<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class OrderMarkedPendingSync extends BaseAggregateEvent implements
    DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;
    private DateTimeImmutable $markedAt;

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        DateTimeImmutable $markedAt = null,
    ): self {
        $instance = new self();
        $instance->sessionId = $sessionId;
        $instance->orderId = $orderId;
        $instance->markedAt = $markedAt ?? new DateTimeImmutable();

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.order_marked_pending_sync';
    }

    final public function getPayload(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id' => $this->orderId->toNative(),
            'marked_at' => $this->markedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->sessionId = SessionId::fromNative($payload['session_id']);
        $this->orderId = OrderId::fromNative($payload['order_id']);
        $this->markedAt = new DateTimeImmutable($payload['marked_at'] ?? '');
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->markedAt;
    }

    final public function getSessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function getOrderId(): OrderId
    {
        return $this->orderId;
    }
}
