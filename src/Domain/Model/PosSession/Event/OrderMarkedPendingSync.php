<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class OrderMarkedPendingSync extends AbstractAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;

    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->sessionId = SessionId::fromNative($array['payload']['session_id']);
        $event->orderId = OrderId::fromNative($array['payload']['order_id']);

        return $event;
    }

    final public static function occur(SessionId $sessionId, OrderId $orderId): self
    {
        $event = new self();
        $event->sessionId = $sessionId;
        $event->orderId   = $orderId;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.order_marked_pending_sync';
    }

    final public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id'   => $this->orderId->toNative(),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    final public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function orderId(): OrderId
    {
        return $this->orderId;
    }
}
