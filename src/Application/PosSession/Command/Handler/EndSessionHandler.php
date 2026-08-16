<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\EndSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

/**
 * EndSessionHandler
 *
 * Handles the EndSession command by ending the POS session.
 */
final class EndSessionHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function __invoke(EndSession $command): void
    {
        $session = $this->sessionRepository->load(SessionId::fromNative($command->sessionId));
        $session->end();
        $this->sessionRepository->store($session);
    }
}
