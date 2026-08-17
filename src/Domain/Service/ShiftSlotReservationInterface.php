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
 * Handlers reserve BEFORE storing the aggregate and compensate (release or
 * transfer back) when the store fails.
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
     * @throws InvariantViolationException when either slot — or the shift id itself — is already claimed
     */
    public function reserveForOpen(string $terminalId, string $cashierId, string $shiftId): void;

    /**
     * Atomically move the shift's cashier slot to a new operator. A no-op
     * when the new operator already holds this shift's slot. A shift that
     * holds no cashier slot is not open here (closed, or never reserved) —
     * refused, so a stale command cannot recreate slots after a close.
     *
     * @return string|null the previous holder's cashier id (for compensation), null if none
     *
     * @throws InvariantViolationException when the new operator holds a slot for another shift, or the shift holds no cashier slot
     */
    public function transferCashier(string $shiftId, string $newCashierId): ?string;

    /**
     * Compensation-only undo of a transferCashier(): move the shift's
     * cashier slot from $ifHeldBy back to $backToCashierId, but ONLY if
     * $ifHeldBy still holds it — if a newer command has already moved or
     * released the slot, this is a no-op (never overwrite committed state).
     * The slot is likewise never given to $backToCashierId if they since
     * acquired a slot for another shift.
     */
    public function compensateTransfer(string $shiftId, string $backToCashierId, string $ifHeldBy): void;

    /**
     * Release every slot held by the shift (close / force-close). Idempotent.
     */
    public function releaseShift(string $shiftId): void;
}
