<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class PaymentRequested extends BaseAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private OrderId $orderId;
    private Money $amount;
    private string $paymentMethod;
    private DateTimeImmutable $requestedAt;

    final public static function occur(
        SessionId $sessionId,
        OrderId $orderId,
        Money $amount,
        string $paymentMethod,
        DateTimeImmutable $requestedAt
    ): self {
        $instance = new self();
        $instance->sessionId = $sessionId;
        $instance->orderId = $orderId;
        $instance->amount = $amount;
        $instance->paymentMethod = $paymentMethod;
        $instance->requestedAt = $requestedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.payment_requested';
    }

    final public function getPayload(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id' => $this->orderId->toNative(),
            'amount' => $this->amount->toArray(),
            'payment_method' => $this->paymentMethod,
            'requested_at' => $this->requestedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->sessionId = SessionId::fromNative($payload['session_id']);
        $this->orderId = OrderId::fromNative($payload['order_id']);
        $this->amount = Money::fromArray($payload['amount']);
        $this->paymentMethod = $payload['payment_method'];
        $this->requestedAt = new DateTimeImmutable($payload['requested_at']);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    final public function getSessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function getOrderId(): OrderId
    {
        return $this->orderId;
    }

    final public function getAmount(): Money
    {
        return $this->amount;
    }

    final public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    final public function getRequestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }
}
