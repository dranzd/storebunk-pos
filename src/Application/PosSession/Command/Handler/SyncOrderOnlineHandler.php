<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\SyncOrderOnline;
use Dranzd\StorebunkPos\Application\Shared\IdempotencyRegistry;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Service\DraftOrderContext;
use Dranzd\StorebunkPos\Domain\Service\OrderingServiceInterface;
use Dranzd\StorebunkPos\Domain\Service\PendingSyncQueue;

final class SyncOrderOnlineHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly OrderingServiceInterface $orderingService,
        private readonly PendingSyncQueue $pendingSyncQueue,
        private readonly IdempotencyRegistry $idempotencyRegistry
    ) {
    }

    final public function __invoke(SyncOrderOnline $command): void
    {
        $commandId = $command->getMessageUuid();

        if ($this->idempotencyRegistry->hasBeenProcessed($commandId)) {
            return;
        }

        $session = $this->sessionRepository->load($command->sessionId());
        $session->syncOrderOnline($command->orderId());
        $this->sessionRepository->store($session);

        $this->orderingService->createDraftOrder(
            $command->orderId(),
            new DraftOrderContext($command->branchId(), $command->customerId())
        );

        $this->pendingSyncQueue->dequeueByOrderId($command->orderId());
        $this->idempotencyRegistry->markAsProcessed($commandId);
    }
}
