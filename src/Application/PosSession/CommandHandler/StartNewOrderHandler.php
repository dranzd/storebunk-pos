<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\CommandHandler;

use Dranzd\StorebunkPos\Domain\Model\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;

final class StartNewOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    final public function __invoke(StartNewOrder $command): void
    {
        $session = $this->sessionRepository->load($command->sessionId());
        $session->startNewOrder($command->orderId());
        $this->sessionRepository->store($session);
    }
}
