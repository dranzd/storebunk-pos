<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\ParkOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;

final class ParkOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    final public function __invoke(ParkOrder $command): void
    {
        $session = $this->sessionRepository->load($command->sessionId());
        $session->parkOrder();
        $this->sessionRepository->store($session);
    }
}
