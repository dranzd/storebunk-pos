<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\DeactivateOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

/**
 * DeactivateOrderHandler
 *
 * Handles the DeactivateOrder command by deactivating the session's active order.
 */
final class DeactivateOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function __invoke(DeactivateOrder $command): void
    {
        $session = $this->sessionRepository->load(SessionId::fromNative($command->sessionId));
        $session->deactivateOrder($command->reason);
        $this->sessionRepository->store($session);
    }
}
