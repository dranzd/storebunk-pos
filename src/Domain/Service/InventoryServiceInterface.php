<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;

interface InventoryServiceInterface
{
    public function convertSoftReservationToHard(OrderId $orderId): void;

    public function releaseReservation(OrderId $orderId): void;

    public function deductInventory(OrderId $orderId): void;

    public function attemptReReservation(OrderId $orderId): bool;
}
