<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class CheckoutInitiated extends BaseAggregateEvent implements
    DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;
    private DateTimeImmutable $initiatedAt;

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        DateTimeImmutable $initiatedAt,
    ): self {
        $instance = new self();
        $instance->sessionId = $sessionId;
        $instance->orderId = $orderId;
        $instance->initiatedAt = $initiatedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.session.checkout_initiated";
    }

    final public function getPayload(): array
    {
        return [
            "session_id" => $this->sessionId->toNative(),
            "order_id" => $this->orderId->toNative(),
            "initiated_at" => $this->initiatedAt->format(
                \DateTimeInterface::ATOM,
            ),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->sessionId = SessionId::fromNative($payload["session_id"]);
        $this->orderId = OrderId::fromNative($payload["order_id"]);
        $this->initiatedAt = new DateTimeImmutable($payload["initiated_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->initiatedAt;
    }

    final public function getSessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function getOrderId(): OrderId
    {
        return $this->orderId;
    }

    final public function getInitiatedAt(): DateTimeImmutable
    {
        return $this->initiatedAt;
    }
}
