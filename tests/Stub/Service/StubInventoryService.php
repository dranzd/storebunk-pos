<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Stub\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;

final class StubInventoryService implements InventoryServiceInterface
{
    private array $softReservations = [];
    private array $confirmedReservations = [];
    private array $deductedInventory = [];
    private bool $reReservationResult = true;

    public function confirmReservation(OrderId $orderId): void
    {
        unset($this->softReservations[$orderId->toNative()]);
        $this->confirmedReservations[$orderId->toNative()] = true;
    }

    public function releaseReservation(OrderId $orderId): void
    {
        unset($this->softReservations[$orderId->toNative()]);
        unset($this->confirmedReservations[$orderId->toNative()]);
    }

    public function deductInventory(OrderId $orderId): void
    {
        unset($this->confirmedReservations[$orderId->toNative()]);
        $this->deductedInventory[$orderId->toNative()] = true;
    }

    public function attemptReReservation(OrderId $orderId): bool
    {
        if ($this->reReservationResult) {
            $this->softReservations[$orderId->toNative()] = true;
        }

        return $this->reReservationResult;
    }

    public function createSoftReservation(OrderId $orderId): void
    {
        $this->softReservations[$orderId->toNative()] = true;
    }

    public function hasConfirmedReservation(OrderId $orderId): bool
    {
        return isset($this->confirmedReservations[$orderId->toNative()]);
    }

    public function isInventoryDeducted(OrderId $orderId): bool
    {
        return isset($this->deductedInventory[$orderId->toNative()]);
    }

    public function setReReservationResult(bool $result): void
    {
        $this->reReservationResult = $result;
    }

    public function hasSoftReservation(OrderId $orderId): bool
    {
        return isset($this->softReservations[$orderId->toNative()]);
    }
}
