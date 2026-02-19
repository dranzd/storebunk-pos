<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class OrderCancelledViaPOS extends AbstractAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;
    private string $reason;
    private DateTimeImmutable $cancelledAt;

    /**
     * @param array<string, mixed> $array
     */
    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->sessionId = SessionId::fromNative($array['payload']['session_id']);
        $event->orderId = OrderId::fromNative($array['payload']['order_id']);
        $event->reason = $array['payload']['reason'];
        $event->cancelledAt = new DateTimeImmutable($array['payload']['cancelled_at']);

        return $event;
    }

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        string $reason,
        DateTimeImmutable $cancelledAt
    ): self {
        $event = new self();
        $event->sessionId = $sessionId;
        $event->orderId = $orderId;
        $event->reason = $reason;
        $event->cancelledAt = $cancelledAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.order_cancelled_via_pos';
    }

    final public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id' => $this->orderId->toNative(),
            'reason' => $this->reason,
            'cancelled_at' => $this->cancelledAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->cancelledAt;
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

    final public function cancelledAt(): DateTimeImmutable
    {
        return $this->cancelledAt;
    }
}
