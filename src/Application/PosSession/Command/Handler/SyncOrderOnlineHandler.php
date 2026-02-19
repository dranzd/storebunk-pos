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

    public function __invoke(SyncOrderOnline $command): void
    {
        $commandId = $command->getMessageUuid();

        if ($this->idempotencyRegistry->hasBeenProcessed($commandId)) {
            return;
        }

        $session = $this->sessionRepository->load($command->getSessionId());
        $session->syncOrderOnline($command->getOrderId());
        $this->sessionRepository->store($session);

        $this->orderingService->createDraftOrder(
            $command->getOrderId(),
            new DraftOrderContext($command->getBranchId(), $command->getCustomerId())
        );

        $this->pendingSyncQueue->dequeueByOrderId($command->getOrderId());
        $this->idempotencyRegistry->markAsProcessed($commandId);
    }
}
