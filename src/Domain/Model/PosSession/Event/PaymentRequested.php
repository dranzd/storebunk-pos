<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class PaymentRequested extends AbstractAggregateEvent implements DomainEventInterface
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
        $event = new self();
        $event->sessionId = $sessionId;
        $event->orderId = $orderId;
        $event->amount = $amount;
        $event->paymentMethod = $paymentMethod;
        $event->requestedAt = $requestedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.payment_requested';
    }

    final public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId->toNative(),
            'order_id' => $this->orderId->toNative(),
            'amount' => $this->amount->toArray(),
            'payment_method' => $this->paymentMethod,
            'requested_at' => $this->requestedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    final public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function orderId(): OrderId
    {
        return $this->orderId;
    }

    final public function amount(): Money
    {
        return $this->amount;
    }

    final public function paymentMethod(): string
    {
        return $this->paymentMethod;
    }

    final public function requestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }
}
