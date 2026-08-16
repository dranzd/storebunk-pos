<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class OrderCreatedOffline extends BaseAggregateEvent implements
    DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;
    private string $commandId;
    private DateTimeImmutable $occurredAt;

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        string $commandId,
    ): self {
        $instance = new self();
        $instance->sessionId = $sessionId;
        $instance->orderId = $orderId;
        $instance->commandId = $commandId;
        $instance->occurredAt = new DateTimeImmutable();

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.order_created_offline';
    }

    final public function getPayload(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id' => $this->orderId->toNative(),
            'command_id' => $this->commandId,
            'occurred_at' => $this->occurredAt->format(
                \DateTimeInterface::ATOM,
            ),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->sessionId = SessionId::fromNative($payload['session_id']);
        $this->orderId = OrderId::fromNative($payload['order_id']);
        $this->commandId = $payload['command_id'];
        $this->occurredAt = new DateTimeImmutable($payload['occurred_at']);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
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
