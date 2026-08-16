<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\ResumeOrder;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

/**
 * ResumeOrderHandler
 *
 * Handles the ResumeOrder command by resuming a parked order in the session.
 */
final class ResumeOrderHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function __invoke(ResumeOrder $command): void
    {
        $session = $this->sessionRepository->load(SessionId::fromNative($command->sessionId));
        $session->resumeOrder(OrderId::fromNative($command->orderId));
        $this->sessionRepository->store($session);
    }
}
