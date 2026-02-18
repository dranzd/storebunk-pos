<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Stub\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Service\OrderingServiceInterface;

final class StubOrderingService implements OrderingServiceInterface
{
    /** @var array<string, int> */
    private array $draftOrders = [];
    private array $confirmedOrders = [];
    private array $cancelledOrders = [];
    private array $fullyPaidOrders = [];

    public function createDraftOrder(OrderId $orderId): void
    {
        $key = $orderId->toNative();
        $this->draftOrders[$key] = ($this->draftOrders[$key] ?? 0) + 1;
    }

    public function draftOrderWasCreated(OrderId $orderId): bool
    {
        return ($this->draftOrders[$orderId->toNative()] ?? 0) > 0;
    }

    public function draftOrderCreationCount(OrderId $orderId): int
    {
        return $this->draftOrders[$orderId->toNative()] ?? 0;
    }

    public function confirmOrder(OrderId $orderId): void
    {
        unset($this->draftOrders[$orderId->toNative()]);
        $this->confirmedOrders[$orderId->toNative()] = true;
    }

    public function cancelOrder(OrderId $orderId, string $reason): void
    {
        unset($this->draftOrders[$orderId->toNative()]);
        unset($this->confirmedOrders[$orderId->toNative()]);
        $this->cancelledOrders[$orderId->toNative()] = $reason;
    }

    public function isOrderFullyPaid(OrderId $orderId): bool
    {
        return isset($this->fullyPaidOrders[$orderId->toNative()]);
    }

    public function markOrderAsFullyPaid(OrderId $orderId): void
    {
        $this->fullyPaidOrders[$orderId->toNative()] = true;
    }

    public function isOrderConfirmed(OrderId $orderId): bool
    {
        return isset($this->confirmedOrders[$orderId->toNative()]);
    }

    public function isOrderCancelled(OrderId $orderId): bool
    {
        return isset($this->cancelledOrders[$orderId->toNative()]);
    }
}
