<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\ReadModel;

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
