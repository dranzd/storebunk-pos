<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class OrderDeactivated extends AbstractAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;
    private string $reason;
    private DateTimeImmutable $deactivatedAt;

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        string $reason,
        DateTimeImmutable $deactivatedAt
    ): self {
        $event = new self();
        $event->sessionId = $sessionId;
        $event->orderId = $orderId;
        $event->reason = $reason;
        $event->deactivatedAt = $deactivatedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.order_deactivated';
    }

    final public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id' => $this->orderId->toNative(),
            'reason' => $this->reason,
            'deactivated_at' => $this->deactivatedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    final public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function orderId(): OrderId
    {
        return $this->orderId;
    }

    final public function reason(): string
    {
        return $this->reason;
    }
}
