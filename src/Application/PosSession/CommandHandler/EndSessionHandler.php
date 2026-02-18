<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\CommandHandler;

use Dranzd\StorebunkPos\Domain\Model\PosSession\Command\EndSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;

final class EndSessionHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    final public function __invoke(EndSession $command): void
    {
        $session = $this->sessionRepository->load($command->sessionId());
        $session->end();
        $this->sessionRepository->store($session);
    }
}
