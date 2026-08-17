<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\ReadModel;

/**
 * Read model backing the multi-terminal enforcement checks. NOTE: the checks
 * are check-then-store over a projection, not an atomic reservation — two
 * truly concurrent commands can both pass (see reported issue 8003). Hosts
 * needing hard uniqueness under concurrency must back this with an
 * authoritative mechanism at their persistence boundary.
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

    /**
     * The map MultiTerminalEnforcementService::assertCashierFreeForShift()
     * consumes: the shift each cashier currently operates.
     *
     * @return array<string, string> cashierId => shiftId
     */
    public function openShiftByCashier(): array;
}
