<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\ParkOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

/**
 * ParkOrderHandler
 *
 * Handles the ParkOrder command by parking the session's active order.
 */
final class ParkOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function __invoke(ParkOrder $command): void
    {
        $session = $this->sessionRepository->load(SessionId::fromNative($command->sessionId));
        $session->parkOrder();
        $this->sessionRepository->store($session);
    }
}
