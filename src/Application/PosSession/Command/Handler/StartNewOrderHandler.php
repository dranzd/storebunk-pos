<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

/**
 * StartNewOrderHandler
 *
 * Handles the StartNewOrder command by starting a new order in the session.
 */
final class StartNewOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function __invoke(StartNewOrder $command): void
    {
        $session = $this->sessionRepository->load(SessionId::fromNative($command->sessionId));
        $session->startNewOrder(OrderId::fromNative($command->orderId));
        $this->sessionRepository->store($session);
    }
}
