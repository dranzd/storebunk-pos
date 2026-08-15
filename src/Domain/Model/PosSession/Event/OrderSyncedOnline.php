<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class OrderSyncedOnline extends BaseAggregateEvent implements
    DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
    ): self {
        $instance = new self();
        $instance->sessionId = $sessionId;
        $instance->orderId = $orderId;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.order_synced_online';
    }

    final public function getPayload(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id' => $this->orderId->toNative(),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->sessionId = SessionId::fromNative($payload['session_id']);
        $this->orderId = OrderId::fromNative($payload['order_id']);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable();
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
