<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateEvent;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrderOffline;
use Dranzd\StorebunkPos\Application\Shared\IdempotencyRegistry;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCreatedOffline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderMarkedPendingSync;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderSyncedOnline;
use Dranzd\StorebunkPos\Domain\Service\PendingSyncQueue;

/**
 * Rebuilds the offline-sync state a host keeps outside the event store — the
 * pending-sync queue and the idempotency registry — by replaying events.
 *
 * A class rather than inline bootstrap code because the rules here are easy
 * to get subtly wrong and impossible to test in a script: a registry entry
 * must carry the same purpose the handler would give it, or every replayed
 * id looks like a collision; and the sync side must NOT be replayed, for the
 * reason given below.
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
                $pendingSyncQueue->enqueue(
                    $event->getSessionId(),
                    $event->getOrderId(),
                    $commandIdsByOrder[$event->getOrderId()->toNative()] ?? ''
                );
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
