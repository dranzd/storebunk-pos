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

    /**
     * The command that synced the order. Required when recording; null only
     * when reconstituting an event stored before it was recorded (see
     * setPayload()), so "unknown" cannot be produced by this build. It is
     * what lets a handler tell a REDELIVERY of the syncing command apart
     * from an unrelated command naming an already-synced order.
     */
    private ?string $commandId = null;

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        string $commandId,
    ): self {
        $instance = new self();
        $instance->sessionId = $sessionId;
        $instance->orderId = $orderId;
        $instance->commandId = $commandId;

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
            'command_id' => $this->commandId,
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->sessionId = SessionId::fromNative($payload['session_id']);
        $this->orderId = OrderId::fromNative($payload['order_id']);
        // Absent on events stored before the command id was recorded. Null
        // means "unknown", not "no command" — a handler must treat it as the
        // old, order-id-only behaviour rather than as a mismatch.
        $this->commandId = isset($payload['command_id']) ? (string) $payload['command_id'] : null;
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

    final public function getCommandId(): ?string
    {
        return $this->commandId;
    }
}
