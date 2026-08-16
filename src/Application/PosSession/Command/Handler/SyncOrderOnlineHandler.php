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

        if ($this->idempotencyRegistry->hasBeenProcessed($commandId)) {
            return;
        }

        $orderId = OrderId::fromNative($command->orderId);

        $session = $this->sessionRepository->load(SessionId::fromNative($command->sessionId));

        // Redelivery of an already-synced order (the registry was rebuilt
        // after a restart, or an earlier attempt failed AFTER the sync event
        // was durably stored). The aggregate is not mutated again, but the
        // draft-order call IS re-issued: the earlier attempt may have died
        // between store() and createDraftOrder(), and skipping it here would
        // silently strand the order with no draft ever created. The port is
        // idempotent per order id by contract, so a repeat is safe. Nothing
        // is marked processed until the port call succeeds, keeping failures
        // loud and retryable.
        if ($session->isOrderSynced($orderId)) {
            $this->orderingService->createDraftOrder($orderId, $command->context);
            $this->pendingSyncQueue->dequeueByOrderId($orderId);
            $this->idempotencyRegistry->markAsProcessed($commandId);

            return;
        }

        $session->syncOrderOnline($orderId);
        $this->sessionRepository->store($session);

        $this->orderingService->createDraftOrder($orderId, $command->context);

        $this->pendingSyncQueue->dequeueByOrderId($orderId);
        $this->idempotencyRegistry->markAsProcessed($commandId);
    }
}
