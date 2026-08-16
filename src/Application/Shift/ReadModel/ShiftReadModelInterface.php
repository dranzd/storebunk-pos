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

    /**
     * The map MultiTerminalEnforcementService::assertTerminalHasNoOpenShift()
     * consumes: one entry per open shift, keyed by terminal.
     *
     * @return array<string, string> terminalId => shiftId
     */
    public function openShiftsByTerminal(): array;

    /**
     * The map MultiTerminalEnforcementService::assertCashierHasNoOpenShift()
     * consumes: the terminal each cashier currently operates an open shift on.
     *
     * @return array<string, string> cashierId => terminalId
     */
    public function activeTerminalByCashier(): array;
}
