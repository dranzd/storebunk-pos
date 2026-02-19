<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class CheckoutInitiated extends AbstractAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;
    private DateTimeImmutable $initiatedAt;

    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->sessionId = SessionId::fromNative($array['payload']['session_id']);
        $event->orderId = OrderId::fromNative($array['payload']['order_id']);
        $event->initiatedAt = new DateTimeImmutable($array['payload']['initiated_at']);

        return $event;
    }

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        DateTimeImmutable $initiatedAt
    ): self {
        $event = new self();
        $event->sessionId = $sessionId;
        $event->orderId = $orderId;
        $event->initiatedAt = $initiatedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.checkout_initiated';
    }

    final public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id' => $this->orderId->toNative(),
            'initiated_at' => $this->initiatedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->initiatedAt;
    }

    final public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function orderId(): OrderId
    {
        return $this->orderId;
    }

    final public function initiatedAt(): DateTimeImmutable
    {
        return $this->initiatedAt;
    }
}
