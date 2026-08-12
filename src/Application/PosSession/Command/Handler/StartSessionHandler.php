<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\PosSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

/**
 * StartSessionHandler
 *
 * Handles the StartSession command by creating a new PosSession aggregate.
 */
final class StartSessionHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function __invoke(StartSession $command): void
    {
        $session = PosSession::start(
            SessionId::fromNative($command->sessionId),
            ShiftId::fromNative($command->shiftId),
            TerminalId::fromNative($command->terminalId),
            CashierId::fromNative($command->cashierId)
        );

        $this->sessionRepository->store($session);
    }
}
