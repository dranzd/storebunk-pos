<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shared;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateEvent;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrderOffline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCreatedOffline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderMarkedPendingSync;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderSyncedOnline;
use Dranzd\StorebunkPos\Domain\Service\PendingSyncQueue;

/**
 * Rebuilds the offline-sync state a host keeps outside the event store — the
 * pending-sync queue and the idempotency registry — by replaying events.
 *
 * Shipped here rather than left as demo code because a host running more
 * than one process HAS to do this, and the rules are easy to get subtly
 * wrong: a registry entry must carry the same purpose the handler would give
 * it, or every replayed id looks like a collision; and the sync side must NOT
 * be replayed, for the reason given below.
 *
 * Note that rebuilding restamps each queue entry's queued-at time, so a host
 * draining the queue by age sees every pending order as fresh after a
 * restart.
 */
final class OfflineStateReplay
{
    /**
     * @param iterable<AggregateEvent> $events in append order
     */
    public static function rebuild(
        iterable $events,
        PendingSyncQueue $pendingSyncQueue,
        IdempotencyRegistry $idempotencyRegistry
    ): void {
        $commandIdsByOrder = [];

        foreach ($events as $event) {
            if ($event instanceof OrderCreatedOffline) {
                $orderId = $event->getOrderId()->toNative();
                $commandIdsByOrder[$orderId] = $event->getCommandId();

                // WITH the purpose the handler uses. A bare mark would record
                // the id as standing for nothing in particular, and the next
                // command carrying it — including a legitimate redelivery of
                // this very command — could not be recognised.
                $idempotencyRegistry->markAsProcessed(
                    $event->getCommandId(),
                    IdempotencyRegistry::purposeFor(
                        StartNewOrderOffline::expectedMessageName(),
                        $orderId
                    )
                );
            } elseif ($event instanceof OrderMarkedPendingSync) {
                $commandId = $commandIdsByOrder[$event->getOrderId()->toNative()] ?? null;
                if ($commandId === null) {
                    // Every OrderMarkedPendingSync trails its own
                    // OrderCreatedOffline in the same stream, so this cannot
                    // happen for a whole stream. Queueing with an empty
                    // command id would record an entry standing for nothing
                    // in particular — the shape this whole guard exists to
                    // remove — so say so instead.
                    throw new \RuntimeException(sprintf(
                        'Cannot rebuild offline state: order "%s" is marked pending sync with no '
                        . 'offline creation before it. The replayed history is incomplete.',
                        $event->getOrderId()->toNative()
                    ));
                }

                $pendingSyncQueue->enqueue($event->getSessionId(), $event->getOrderId(), $commandId);
            } elseif ($event instanceof OrderSyncedOnline) {
                $pendingSyncQueue->dequeueByOrderId($event->getOrderId());

                // The sync command is deliberately NOT marked processed, even
                // though OrderSyncedOnline now records its id. Marking it
                // would make a redelivered sync return at the registry — and
                // that redelivery is what re-issues the draft-order call for
                // an attempt that died between storing the event and calling
                // the ordering port. Healing a stranded order matters more
                // here than catching a reused id, and the aggregate still
                // tells a redelivery from an unrelated command by that same
                // recorded id.
                continue;
            }
        }
    }
}
