<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use DateTimeImmutable;
use Dranzd\Common\Cqrs\Application\Command\Bus as CommandBus;
use Dranzd\Common\Cqrs\Application\Command\Exception\ExecutionFailedException;
use Dranzd\StorebunkPos\Application\PosSession\Command\CancelOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\DeactivateOrder;
use Dranzd\StorebunkPos\Application\PosSession\ReadModel\PosSessionReadModelInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

final class DraftLifecycleService
{
    private const INACTIVITY_TTL_MINUTES = 15;
    private const AUTO_CANCEL_THRESHOLD_MINUTES = 60;

    public function __construct(
        private readonly PosSessionReadModelInterface $sessionReadModel,
        private readonly CommandBus $commandBus
    ) {
    }

    public function checkAndDeactivateInactiveOrders(DateTimeImmutable $currentTime): void
    {
        foreach ($this->sessionReadModel->getSessionsWithActiveOrder() as $row) {
            if ($this->shouldDeactivateOrder($row['last_activity_at'], $currentTime)) {
                try {
                    $this->commandBus->dispatch(
                        new DeactivateOrder(
                            $row['session_id'],
                            'Automatically deactivated due to inactivity'
                        )
                    );
                } catch (ExecutionFailedException $exception) {
                    // Best-effort sweep: a session may legitimately refuse
                    // (e.g. its order is mid-checkout, which the aggregate
                    // guards against). Skip it and keep sweeping the rest.
                    // Anything other than a domain refusal (repository or
                    // storage failure, programming error) must surface.
                    if (!$exception->getPrevious() instanceof InvariantViolationException) {
                        throw $exception;
                    }
                }
            }
        }
    }

    public function checkAndCancelExpiredOrders(DateTimeImmutable $currentTime): void
    {
        foreach ($this->sessionReadModel->getSessionsWithActiveOrder() as $row) {
            if ($this->isOrderExpired($row['last_activity_at'], $currentTime)) {
                try {
                    $this->commandBus->dispatch(
                        new CancelOrder(
                            $row['session_id'],
                            'Automatically cancelled due to expiry'
                        )
                    );
                } catch (ExecutionFailedException $exception) {
                    // Same best-effort discipline as the inactivity sweep:
                    // skip sessions that refuse on a domain invariant, but
                    // surface every other failure.
                    if (!$exception->getPrevious() instanceof InvariantViolationException) {
                        throw $exception;
                    }
                }
            }
        }
    }

    public function isOrderExpired(DateTimeImmutable $lastActivityTime, DateTimeImmutable $currentTime): bool
    {
        $diffInMinutes = ($currentTime->getTimestamp() - $lastActivityTime->getTimestamp()) / 60;

        return $diffInMinutes > self::AUTO_CANCEL_THRESHOLD_MINUTES;
    }

    public function shouldDeactivateOrder(DateTimeImmutable $lastActivityTime, DateTimeImmutable $currentTime): bool
    {
        $diffInMinutes = ($currentTime->getTimestamp() - $lastActivityTime->getTimestamp()) / 60;

        return $diffInMinutes > self::INACTIVITY_TTL_MINUTES;
    }
}
