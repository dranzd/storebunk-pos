<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class OrderDeactivated extends BaseAggregateEvent implements
    DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;
    private string $reason;
    private DateTimeImmutable $deactivatedAt;

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        string $reason,
        DateTimeImmutable $deactivatedAt,
    ): self {
        $instance = new self();
        $instance->sessionId = $sessionId;
        $instance->orderId = $orderId;
        $instance->reason = $reason;
        $instance->deactivatedAt = $deactivatedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.session.order_deactivated";
    }

    final public function getPayload(): array
    {
        return [
            "session_id" => $this->sessionId->toNative(),
            "order_id" => $this->orderId->toNative(),
            "reason" => $this->reason,
            "deactivated_at" => $this->deactivatedAt->format(
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
        $this->reason = $payload["reason"];
        $this->deactivatedAt = new DateTimeImmutable(
            $payload["deactivated_at"],
        );
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    final public function getSessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function getOrderId(): OrderId
    {
        return $this->orderId;
    }

    final public function getReason(): string
    {
        return $this->reason;
    }

    final public function getDeactivatedAt(): DateTimeImmutable
    {
        return $this->deactivatedAt;
    }
}
