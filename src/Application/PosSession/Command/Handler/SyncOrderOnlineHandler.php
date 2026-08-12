<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\SyncOrderOnline;
use Dranzd\StorebunkPos\Application\Shared\IdempotencyRegistry;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Service\DraftOrderContext;
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
        $session->syncOrderOnline($orderId);
        $this->sessionRepository->store($session);

        $this->orderingService->createDraftOrder(
            $orderId,
            new DraftOrderContext($command->branchId, $command->customerId)
        );

        $this->pendingSyncQueue->dequeueByOrderId($orderId);
        $this->idempotencyRegistry->markAsProcessed($commandId);
    }
}
