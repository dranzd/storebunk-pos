<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\ReadModel;

interface PosSessionReadModelInterface
{
    /**
     * Return all sessions that currently have an active order, along with the
     * timestamp of the order's last activity.
     *
     * Each entry must contain at minimum:
     *   - 'session_id'         string
     *   - 'last_activity_at'   \DateTimeImmutable
     *
     * @return array<int, array{session_id: string, last_activity_at: \DateTimeImmutable}>
     */
    public function getSessionsWithActiveOrder(): array;

    /**
     * Return the session IDs of all sessions that are still active (not ended)
     * for the given shift.
     *
     * @return array<int, string>
     */
    public function findActiveByShiftId(string $shiftId): array;
}
