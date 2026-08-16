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
    /** @var array<string, array<string, mixed>> */
    private array $draftOrderContexts = [];

    private ?\Throwable $nextDraftOrderFailure = null;

    final public function createDraftOrder(OrderId $orderId, array $context): void
    {
        if ($this->nextDraftOrderFailure !== null) {
            $failure = $this->nextDraftOrderFailure;
            $this->nextDraftOrderFailure = null;
            throw $failure;
        }

        $key = $orderId->toNative();
        $this->draftOrders[$key] = ($this->draftOrders[$key] ?? 0) + 1;
        $this->draftOrderContexts[$key] = $context;
    }

    /**
     * Make the NEXT createDraftOrder() call fail (once) — simulates the
     * Ordering BC being unavailable after the sync event was stored.
     */
    public function failNextDraftOrderCreation(\Throwable $failure): void
    {
        $this->nextDraftOrderFailure = $failure;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastDraftOrderContext(OrderId $orderId): ?array
    {
        return $this->draftOrderContexts[$orderId->toNative()] ?? null;
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
