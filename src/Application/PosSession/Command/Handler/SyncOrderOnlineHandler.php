<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\SyncOrderOnline;
use Dranzd\StorebunkPos\Application\Shared\IdempotencyRegistry;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Service\OrderingServiceInterface;
use Dranzd\StorebunkPos\Domain\Service\PendingSyncQueue;

/**
 * SyncOrderOnlineHandler
 *
 * Handles the SyncOrderOnline command idempotently, syncing an offline-started
 * order online, creating its draft order and dequeueing the pending sync.
 */
final class SyncOrderOnlineHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly OrderingServiceInterface $orderingService,
        private readonly PendingSyncQueue $pendingSyncQueue,
        private readonly IdempotencyRegistry $idempotencyRegistry
    ) {
    }

    public function __invoke(SyncOrderOnline $command): void
    {
        $commandId = $command->getMessageUuid();
        $orderId = OrderId::fromNative($command->orderId);

        // What this command does — a sync of THIS order. An id already spent
        // on a different command (a create of the same order, say) is a
        // collision the registry refuses rather than swallows.
        $purpose = IdempotencyRegistry::purposeFor(SyncOrderOnline::expectedMessageName(), $orderId->toNative());

        if ($this->idempotencyRegistry->hasBeenProcessed($commandId, $purpose)) {
            return;
        }

        $session = $this->sessionRepository->load(SessionId::fromNative($command->sessionId));

        // Redelivery of THIS command for an order it already synced (the
        // registry was rebuilt after a restart, or an earlier attempt failed
        // AFTER the sync event was durably stored). The aggregate is not
        // mutated again, but the draft-order call IS re-issued: the earlier
        // attempt may have died between store() and createDraftOrder(), and
        // skipping it here would silently strand the order with no draft ever
        // created. The port is idempotent per order id by contract, so a
        // repeat is safe. Nothing is marked processed until the port call
        // succeeds, keeping failures loud and retryable.
        //
        // Keyed on the COMMAND, not just the order: an unrelated command
        // naming an order that merely happens to be synced is not a
        // redelivery, and falls through to the pending-sync refusal below.
        if ($session->wasSyncedByCommand($orderId, $commandId)) {
            $this->orderingService->createDraftOrder($orderId, $command->context);
            $this->pendingSyncQueue->dequeueByOrderId($orderId);
            $this->idempotencyRegistry->markAsProcessed($commandId, $purpose);

            return;
        }

        $session->syncOrderOnline($orderId, $commandId);
        $this->sessionRepository->store($session);

        $this->orderingService->createDraftOrder($orderId, $command->context);

        $this->pendingSyncQueue->dequeueByOrderId($orderId);
        $this->idempotencyRegistry->markAsProcessed($commandId, $purpose);
    }
}
