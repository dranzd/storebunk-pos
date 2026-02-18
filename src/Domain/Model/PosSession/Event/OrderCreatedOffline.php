<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class OrderCreatedOffline extends AbstractAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;
    private string $commandId;

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        string $commandId
    ): self {
        $event = new self();
        $event->sessionId = $sessionId;
        $event->orderId   = $orderId;
        $event->commandId = $commandId;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.order_created_offline';
    }

    final public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id'   => $this->orderId->toNative(),
            'command_id' => $this->commandId,
        ];
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

    final public function getCommandId(): string
    {
        return $this->commandId;
    }
}
