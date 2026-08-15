<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;

interface OrderingServiceInterface
{
    /**
     * @param array<string, mixed> $context Opaque, consumer-owned context.
     *        POS forwards it verbatim and never reads, validates, or defaults
     *        its keys — the schema belongs to the consuming application
     *        (ADR-006).
     */
    public function createDraftOrder(OrderId $orderId, array $context): void;

    public function confirmOrder(OrderId $orderId): void;

    public function cancelOrder(OrderId $orderId, string $reason): void;

    public function isOrderFullyPaid(OrderId $orderId): bool;
}
