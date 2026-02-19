<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrderOffline;
use Dranzd\StorebunkPos\Application\Shared\IdempotencyRegistry;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Service\PendingSyncQueue;

final class StartNewOrderOfflineHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly PendingSyncQueue $pendingSyncQueue,
        private readonly IdempotencyRegistry $idempotencyRegistry
    ) {
    }

    final public function __invoke(StartNewOrderOffline $command): void
    {
        $commandId = $command->getMessageUuid();

        if ($this->idempotencyRegistry->hasBeenProcessed($commandId)) {
            return;
        }

        if ($this->pendingSyncQueue->hasByOrderId($command->orderId())) {
            return;
        }

        $session = $this->sessionRepository->load($command->sessionId());
        $session->startNewOrderOffline($command->orderId(), $commandId);
        $this->sessionRepository->store($session);

        $this->pendingSyncQueue->enqueue(
            $command->sessionId(),
            $command->orderId(),
            $commandId
        );

        $this->idempotencyRegistry->markAsProcessed($commandId);
    }
}
