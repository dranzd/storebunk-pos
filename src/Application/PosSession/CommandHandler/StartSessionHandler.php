<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\CommandHandler;

use Dranzd\StorebunkPos\Domain\Model\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\PosSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;

final class StartSessionHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    final public function __invoke(StartSession $command): void
    {
        $session = PosSession::start(
            $command->sessionId(),
            $command->shiftId(),
            $command->terminalId()
        );

        $this->sessionRepository->store($session);
    }
}
