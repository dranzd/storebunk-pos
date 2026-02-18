<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\ResumeOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;

final class ResumeOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    final public function __invoke(ResumeOrder $command): void
    {
        $session = $this->sessionRepository->load($command->sessionId());
        $session->resumeOrder($command->orderId());
        $this->sessionRepository->store($session);
    }
}
