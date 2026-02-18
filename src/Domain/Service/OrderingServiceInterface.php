<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;

interface OrderingServiceInterface
{
    public function createDraftOrder(OrderId $orderId): void;

    public function confirmOrder(OrderId $orderId): void;

    public function cancelOrder(OrderId $orderId, string $reason): void;

    public function isOrderFullyPaid(OrderId $orderId): bool;
}
