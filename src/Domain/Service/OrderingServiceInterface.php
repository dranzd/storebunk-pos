<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;

interface OrderingServiceInterface
{
    /**
     * @param DraftOrderContext $context Opaque, consumer-provided context.
     *        POS forwards it verbatim and never reads its keys (ADR-006).
     */
    public function createDraftOrder(OrderId $orderId, DraftOrderContext $context): void;

    public function confirmOrder(OrderId $orderId): void;

    public function cancelOrder(OrderId $orderId, string $reason): void;

    public function isOrderFullyPaid(OrderId $orderId): bool;
}
