<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Model\PosSession\PosSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;

final class DraftLifecycleService
{
    private const INACTIVITY_TTL_MINUTES = 15;
    private const AUTO_CANCEL_THRESHOLD_MINUTES = 60;

    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function checkAndDeactivateInactiveOrders(DateTimeImmutable $currentTime): void
    {
    }

    public function checkAndCancelExpiredOrders(DateTimeImmutable $currentTime): void
    {
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
