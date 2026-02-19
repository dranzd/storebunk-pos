<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\DeactivateOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;

final class DeactivateOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    final public function __invoke(DeactivateOrder $command): void
    {
        $session = $this->sessionRepository->load($command->sessionId());
        $session->deactivateOrder($command->reason());
        $this->sessionRepository->store($session);
    }
}
