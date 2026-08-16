<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;

interface OrderingServiceInterface
{
    /**
     * MUST be idempotent per order id: offline-sync delivery is
     * at-least-once, so a retry after a partial failure may re-invoke this
     * with the same order id (and the same context). Creating a second
     * draft for the same order id is a consumer-side defect.
     *
     * Redeliveries of one logical command (same deterministic message uuid)
     * are assumed to carry an identical context; POS does not detect or
     * reconcile divergence between deliveries.
     *
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
