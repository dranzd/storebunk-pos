<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

/**
 * The slot bookkeeping behind ShiftSlotReservationInterface, as pure
 * state-in / state-out transitions.
 *
 * Every implementation of the port needs the same algebra and differs only in
 * where the state lives and how it is made atomic (arrays in one process, a
 * locked file, a database row, …). Keeping the transitions here means the
 * one-shift-per-terminal / one-shift-per-cashier rules — and the prepare /
 * commit / abort protocol that keeps slots consistent with committed
 * aggregates — have a single home.
 *
 * @phpstan-type SlotState array{
 *     terminals: array<string, string>,
 *     cashiers: array<string, string>,
 *     pending: array<string, string>
 * }
 */
final class ShiftSlotBook
{
    public function __construct(
        private readonly MultiTerminalEnforcementService $multiTerminalEnforcement = new MultiTerminalEnforcementService()
    ) {
    }

    /**
     * @return SlotState
     */
    public function emptyState(): array
    {
        return ['terminals' => [], 'cashiers' => [], 'pending' => []];
    }

    /**
     * @param SlotState $slots
     *
     * @return SlotState
     */
    public function reserveForOpen(array $slots, string $terminalId, string $cashierId, string $shiftId): array
    {
        $held = $this->heldCashierSlots($slots);

        if (in_array($shiftId, $slots['terminals'], true) || in_array($shiftId, $held, true)) {
            throw InvariantViolationException::withMessage(
                sprintf('Shift "%s" already holds open-shift slots', $shiftId)
            );
        }
        $this->multiTerminalEnforcement->assertTerminalHasNoOpenShift(
            TerminalId::fromNative($terminalId),
            $slots['terminals']
        );
        $this->multiTerminalEnforcement->assertCashierHasNoOpenShift($cashierId, $held);

        $slots['terminals'][$terminalId] = $shiftId;
        $slots['cashiers'][$cashierId]   = $shiftId;

        return $slots;
    }

    /**
     * Claim the incoming operator's slot WITHOUT releasing the outgoing one.
     *
     * @param SlotState $slots
     *
     * @return SlotState
     */
    public function prepareTransfer(array $slots, string $shiftId, string $newCashierId): array
    {
        $currentHolder = $this->committedHolderOf($slots, $shiftId);
        if ($currentHolder === null) {
            throw InvariantViolationException::withMessage(
                sprintf('Shift "%s" holds no cashier slot; it is not open here', $shiftId)
            );
        }
        // The in-flight guard comes FIRST, before the "already the holder"
        // no-op: a second command whose target happens to be the committed
        // holder would otherwise slip through, persist its own change, and
        // then be silently overwritten when the in-flight transfer commits —
        // leaving the slots naming one cashier and the events another.
        if (in_array($shiftId, $slots['pending'], true)) {
            throw InvariantViolationException::withMessage(
                sprintf('Shift "%s" already has a cashier transfer in flight', $shiftId)
            );
        }
        if ($currentHolder === $newCashierId) {
            return $slots;
        }
        $this->multiTerminalEnforcement->assertCashierFreeForShift(
            $newCashierId,
            $shiftId,
            $this->heldCashierSlots($slots)
        );

        $slots['pending'][$newCashierId] = $shiftId;

        return $slots;
    }

    /**
     * @param SlotState $slots
     *
     * @return SlotState
     */
    public function commitTransfer(array $slots, string $shiftId, string $newCashierId): array
    {
        if (($slots['pending'][$newCashierId] ?? null) !== $shiftId) {
            return $slots;
        }

        unset($slots['pending'][$newCashierId]);
        $slots['cashiers'] = $this->withoutShift($slots['cashiers'], $shiftId);
        $slots['cashiers'][$newCashierId] = $shiftId;

        return $slots;
    }

    /**
     * @param SlotState $slots
     *
     * @return SlotState
     */
    public function abortTransfer(array $slots, string $shiftId, string $newCashierId): array
    {
        if (($slots['pending'][$newCashierId] ?? null) !== $shiftId) {
            return $slots;
        }

        unset($slots['pending'][$newCashierId]);

        return $slots;
    }

    /**
     * @param SlotState $slots
     *
     * @return SlotState
     */
    public function releaseShift(array $slots, string $shiftId): array
    {
        return [
            'terminals' => $this->withoutShift($slots['terminals'], $shiftId),
            'cashiers'  => $this->withoutShift($slots['cashiers'], $shiftId),
            'pending'   => $this->withoutShift($slots['pending'], $shiftId),
        ];
    }

    /**
     * The state the slots SHOULD have, given the committed open shifts.
     *
     * @param array<string, array{terminal_id: string, cashier_id: string}> $openShiftsById
     *
     * @return SlotState
     */
    public function stateFor(array $openShiftsById): array
    {
        $slots = $this->emptyState();
        foreach ($openShiftsById as $shiftId => $shift) {
            $slots['terminals'][$shift['terminal_id']] = (string) $shiftId;
            $slots['cashiers'][$shift['cashier_id']]   = (string) $shiftId;
        }

        return $slots;
    }

    /**
     * Refuse a history that already breaks the invariant: two open shifts
     * holding one terminal or one cashier. Rebuilding slots from it would
     * silently keep the last shift and leave the other permanently
     * unassignable, so a deliberate reconciliation reports it instead.
     *
     * Kept OUT of stateFor() on purpose: seeding runs automatically on every
     * bootstrap, and a recovery tool that cannot start is not a recovery
     * tool. Seeding stays permissive; only reconcile() asserts.
     *
     * @param array<string, array{terminal_id: string, cashier_id: string}> $openShiftsById
     */
    public function assertConsistent(array $openShiftsById): void
    {
        $terminals = [];
        $cashiers  = [];
        foreach ($openShiftsById as $shiftId => $shift) {
            $this->assertUnclaimed($terminals, $shift['terminal_id'], (string) $shiftId, 'terminal');
            $this->assertUnclaimed($cashiers, $shift['cashier_id'], (string) $shiftId, 'cashier');
            $terminals[$shift['terminal_id']] = (string) $shiftId;
            $cashiers[$shift['cashier_id']]   = (string) $shiftId;
        }
    }

    /**
     * @param array<string, string> $bucket
     */
    private function assertUnclaimed(array $bucket, string $key, string $shiftId, string $what): void
    {
        $claimedBy = $bucket[$key] ?? null;
        if ($claimedBy !== null && $claimedBy !== $shiftId) {
            throw InvariantViolationException::withMessage(sprintf(
                'Cannot reconcile: %s "%s" is held by two open shifts, "%s" and "%s". '
                . 'Close one of them before reconciling.',
                $what,
                $key,
                $claimedBy,
                $shiftId
            ));
        }
    }

    /**
     * How many slot entries differ between two states — what a reconciliation
     * reports as corrected.
     *
     * @param SlotState $before
     * @param SlotState $after
     */
    public function correctionCount(array $before, array $after): int
    {
        return $this->bucketDifference($before['terminals'], $after['terminals'])
            + $this->bucketDifference($before['cashiers'], $after['cashiers'])
            + $this->bucketDifference($before['pending'], $after['pending']);
    }

    /**
     * Committed and in-flight claims together: what "this cashier is busy"
     * means for every occupancy rule.
     *
     * @param SlotState $slots
     *
     * @return array<string, string> cashierId => shiftId
     */
    private function heldCashierSlots(array $slots): array
    {
        return $slots['cashiers'] + $slots['pending'];
    }

    /**
     * @param SlotState $slots
     */
    private function committedHolderOf(array $slots, string $shiftId): ?string
    {
        $holder = array_search($shiftId, $slots['cashiers'], true);

        return $holder === false ? null : (string) $holder;
    }

    /**
     * @param array<string, string> $bucket
     *
     * @return array<string, string>
     */
    private function withoutShift(array $bucket, string $shiftId): array
    {
        return array_filter($bucket, static fn(string $heldShiftId): bool => $heldShiftId !== $shiftId);
    }

    /**
     * @param array<string, string> $before
     * @param array<string, string> $after
     */
    private function bucketDifference(array $before, array $after): int
    {
        $changed = 0;
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changed++;
            }
        }
        foreach ($before as $key => $value) {
            if (!isset($after[$key])) {
                $changed++;
            }
        }

        return $changed;
    }
}
