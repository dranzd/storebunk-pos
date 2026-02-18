<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Stub\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;

final class StubInventoryService implements InventoryServiceInterface
{
    private array $softReservations = [];
    private array $hardReservations = [];
    private array $deductedInventory = [];

    public function convertSoftReservationToHard(OrderId $orderId): void
    {
        unset($this->softReservations[$orderId->toNative()]);
        $this->hardReservations[$orderId->toNative()] = true;
    }

    public function releaseReservation(OrderId $orderId): void
    {
        unset($this->softReservations[$orderId->toNative()]);
        unset($this->hardReservations[$orderId->toNative()]);
    }

    public function deductInventory(OrderId $orderId): void
    {
        unset($this->hardReservations[$orderId->toNative()]);
        $this->deductedInventory[$orderId->toNative()] = true;
    }

    public function createSoftReservation(OrderId $orderId): void
    {
        $this->softReservations[$orderId->toNative()] = true;
    }

    public function hasHardReservation(OrderId $orderId): bool
    {
        return isset($this->hardReservations[$orderId->toNative()]);
    }

    public function isInventoryDeducted(OrderId $orderId): bool
    {
        return isset($this->deductedInventory[$orderId->toNative()]);
    }
}
