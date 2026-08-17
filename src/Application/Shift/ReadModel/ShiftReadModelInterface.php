<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\ReadModel;

/**
 * Query-side view of shifts. Presentation/query state only — the concurrency
 * authority for the multi-terminal invariants is
 * {@see \Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface}.
 */
interface ShiftReadModelInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function getShift(string $shiftId): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOpenShifts(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getShiftsByTerminal(string $terminalId): array;
}
