<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;

interface InventoryServiceInterface
{
    /**
     * Signal to the inventory BC that the reservation for this order has been confirmed at checkout.
     *
     * Adapter note: This is intentionally a no-op until the inventory BC exposes a matching
     * confirmation operation. The consumer is responsible for mapping this call to whatever
     * concept their inventory system uses (e.g. confirmReservation, lockReservation, etc.).
     * If the inventory BC auto-confirms on order completion, the adapter may safely do nothing here.
     */
    public function confirmReservation(OrderId $orderId): void;

    public function releaseReservation(OrderId $orderId): void;

    public function deductInventory(OrderId $orderId): void;

    public function attemptReReservation(OrderId $orderId): bool;
}
