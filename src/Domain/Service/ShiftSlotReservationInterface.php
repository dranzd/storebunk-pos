<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

/**
 * Atomic uniqueness authority for the multi-terminal invariants: one open
 * shift per terminal, one open shift per cashier (reported issue 8003).
 *
 * Each open shift holds two slots — its terminal and its operating cashier.
 * Implementations MUST make every method atomic against concurrent callers
 * (database uniqueness, SETNX, a shared file lock, …); an eventually
 * consistent read model or a process-local lock is NOT a valid backing.
 *
 * Slots are only ever GIVEN UP once the matching aggregate change is durable.
 * Moving a shift's cashier therefore runs as prepare → (store) → commit, with
 * abort on failure: the outgoing cashier keeps their slot for the whole
 * in-flight window, so no other command can hand them a second open shift on
 * the strength of a change that has not committed yet. A prepared-but-never-
 * resolved claim (the process died mid-command) blocks that cashier until
 * {@see self::reconcile()} rebuilds the slots from the committed aggregates.
 */
interface ShiftSlotReservationInterface
{
    /**
     * Atomically claim the terminal slot and the cashier slot for a shift
     * about to open. All-or-nothing: on refusal neither slot is taken. The
     * SHIFT id is claimed too — a shift id that already holds slots is
     * refused, so two racing opens sharing an id cannot both claim and a
     * loser's release can only ever drop its own claim.
     *
     * Opening only ADDS claims, so it needs no prepare/commit pair: a failed
     * store is undone by {@see self::releaseShift()}, which gives up nothing
     * another command could have been waiting on.
     *
     * @throws InvariantViolationException when either slot — or the shift id itself — is already claimed
     */
    public function reserveForOpen(string $terminalId, string $cashierId, string $shiftId): void;

    /**
     * Claim the shift's cashier slot FOR the incoming operator without
     * releasing the outgoing one: until the caller commits, both are held, so
     * neither can pick up another open shift. A no-op when the incoming
     * operator already holds this shift's committed slot.
     *
     * Refused when: the shift holds no committed cashier slot (closed, or
     * never reserved — a stale command must not recreate slots after a
     * close); the incoming operator holds a slot for another shift; or the
     * shift already has a transfer in flight.
     *
     * @throws InvariantViolationException on any of the refusals above
     */
    public function prepareTransfer(string $shiftId, string $newCashierId): void;

    /**
     * Make a prepared transfer final: the outgoing operator's slot is dropped
     * and the incoming operator becomes the shift's committed holder. Call
     * only after the aggregate change is durable. Idempotent — a no-op when
     * no matching preparation is in flight.
     */
    public function commitTransfer(string $shiftId, string $newCashierId): void;

    /**
     * Discard a prepared transfer: the incoming operator's claim is dropped
     * and the outgoing operator keeps the slot they never lost. Idempotent,
     * and — unlike a compensating re-transfer — it can never fail to restore
     * the previous state, because that state was never given up.
     */
    public function abortTransfer(string $shiftId, string $newCashierId): void;

    /**
     * Release every slot held by the shift (close / force-close). Idempotent.
     */
    public function releaseShift(string $shiftId): void;

    /**
     * Rebuild the slots to match the committed aggregates exactly, dropping
     * anything that has no open shift behind it — the recovery path for slots
     * left uncertain by a failure between persistence and slot bookkeeping
     * (a store that committed but whose release threw, a process killed
     * between prepare and commit).
     *
     * MAINTENANCE OPERATION: it discards in-flight preparations, so it must
     * run only when no shift command is executing.
     *
     * @param array<string, array{terminal_id: string, cashier_id: string}> $openShiftsById
     *        authoritative open shifts, keyed by shift id
     *
     * @return int the number of slot entries added, removed or corrected
     */
    public function reconcile(array $openShiftsById): int;
}
