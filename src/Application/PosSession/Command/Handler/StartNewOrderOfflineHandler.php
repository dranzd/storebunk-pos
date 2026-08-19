<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrderOffline;
use Dranzd\StorebunkPos\Application\Shared\IdempotencyRegistry;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Service\PendingSyncQueue;

/**
 * StartNewOrderOfflineHandler
 *
 * Handles the StartNewOrderOffline command idempotently, starting the order
 * offline and enqueueing it for later online sync.
 */
final class StartNewOrderOfflineHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly PendingSyncQueue $pendingSyncQueue,
        private readonly IdempotencyRegistry $idempotencyRegistry
    ) {
    }

    public function __invoke(StartNewOrderOffline $command): void
    {
        $commandId = $command->getMessageUuid();

        if ($this->idempotencyRegistry->hasBeenProcessed($commandId)) {
            return;
        }

        $sessionId = SessionId::fromNative($command->sessionId);
        $orderId = OrderId::fromNative($command->orderId);

        if ($this->pendingSyncQueue->hasByOrderId($orderId)) {
            return;
        }

        $session = $this->sessionRepository->load($sessionId);

        // THIS command already created this order — a redelivery, not a
        // reuse. The two are told apart by the command id, which
        // OrderCreatedOffline persists: same order and same command is a
        // repeat to absorb, same order under a DIFFERENT command is an id
        // being reused, which the aggregate refuses below.
        //
        // A host that replays command ids into the registry never gets here;
        // one that does not (or that crashed between store() and enqueue())
        // does, and re-queueing an order that has not synced is what stops
        // that order being stranded — the same healing the sync handler does
        // in the mirror position.
        if ($session->wasStartedByCommand($orderId, $commandId)) {
            if (!$session->isOrderSynced($orderId)) {
                $this->pendingSyncQueue->enqueue($sessionId, $orderId, $commandId);
            }
            $this->idempotencyRegistry->markAsProcessed($commandId);

            return;
        }

        $session->startNewOrderOffline($orderId, $commandId);
        $session->markOrderPendingSync($orderId);
        $this->sessionRepository->store($session);

        $this->pendingSyncQueue->enqueue(
            $sessionId,
            $orderId,
            $commandId
        );

        $this->idempotencyRegistry->markAsProcessed($commandId);
    }
}
