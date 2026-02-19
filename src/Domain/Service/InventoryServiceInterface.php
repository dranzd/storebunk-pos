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

    /**
     * Signal to the inventory BC that stock for this order has been consumed (goods left the store).
     *
     * Adapter note: The inventory BC does not have a direct "deduct stock" command. The correct
     * mapping is to call ReservationManager::fulfillReservation(ReservationId) for each active
     * reservation associated with this order. The adapter must first resolve
     * OrderId → ReservationId(s) via a read model query (e.g.
     * ReservationManager::getActiveReservationsByReference()). Fulfilling a reservation transitions
     * its state from Active → Fulfilled, which is the inventory BC's model for stock consumption.
     */
    public function fulfillOrderReservation(OrderId $orderId): void;

    public function attemptReReservation(OrderId $orderId): bool;
}
