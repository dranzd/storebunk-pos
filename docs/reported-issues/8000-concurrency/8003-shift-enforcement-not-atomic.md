# 8003 — Shift Enforcement Is Check-Then-Store, Not Atomic Under Concurrency

**Type:** Architecture
**Status:** Resolved
**Severity:** High
**Reported:** 2026-08-17
**Resolved:** 2026-08-17
**Affects:** src/Application/Shift/Command/Handler/OpenShiftHandler.php, src/Application/Shift/Command/Handler/AssignShiftHandler.php, src/Application/Shift/Command/Handler/UnassignShiftHandler.php, src/Application/Shift/ReadModel/ShiftReadModelInterface.php

---

## Issue

The one-shift-per-terminal / one-shift-per-cashier enforcement (issues 8002 and the assign/unassign follow-up) reads a projection and then stores a shift as two separate steps. Two truly concurrent requests can both observe a state that permits them and both persist, recreating the forbidden state. Per-aggregate optimistic locking cannot catch this because the two shifts are different aggregates with different ids.

---

## Findings

- `OpenShiftHandler.php` asserts against `ShiftReadModelInterface` maps, then `ShiftRepositoryInterface::store()` appends to a NEW aggregate stream — no shared serialization point between check and store. The same shape applies to the assign/unassign guards.
- Within one PHP process the command bus is synchronous, so the race requires genuinely concurrent processes/requests. The demo CLI can hit it: two simultaneous `shift open` processes each replay events at bootstrap, both pass the check, and `FileEventStore`'s locked merge-on-save happily persists both `ShiftOpened` streams.
- This is the classic cross-aggregate invariant problem in event-sourced systems: aggregate boundaries give atomicity per stream only; set-wide uniqueness needs an authoritative mechanism outside the aggregates.
- Raised by external review on 2026-08-17; verified against the code. The serial-path enforcement (8002) is real and tested; only the concurrent window is open.

---

## Root Cause

The library owns domain and application layers but deliberately owns no persistence infrastructure, so there is no transactional boundary in which "check + store" can be made atomic. The read-model projection was the only in-library state available to check against, and a projection can never be a concurrency authority.

---

## Recommended Action

Design decision required — options:

**Option A (preferred): a uniqueness-reservation port.** Define a small `ShiftSlotReservationInterface` (reserve terminal slot + cashier slot, release on close/force-close) that `OpenShiftHandler`/`AssignShiftHandler` call BEFORE storing; the host implements it atomically (SQL unique constraint, Redis SETNX, advisory lock — its choice). The in-memory/demo adapters get a straightforward implementation (demo: a lock-protected sidecar file). Keeps aggregates clean, makes the guarantee explicit and testable.

**Option B: accept and document.** Keep best-effort enforcement; document that hosts must provide hard uniqueness at their boundary (the interface docblock and domain-model doc already say this since 2026-08-17). Zero code, but the demo remains raceable.

**Option C: demo-only serialization.** Serialize check+append under the demo's existing event-store sidecar lock. Fixes only the demo; the library contract stays best-effort (documented).

---

## Open Questions for Owner

**Q1. Which approach?**
- **(a)** Option A — reservation port with host-provided atomicity. **Recommended.**
- **(b)** Option B — document as host responsibility, no code.
- **(c)** Option C — demo-only serialization, library stays documented best-effort.

---

## Owner Response

**Decision:** Accept
**Date answered:** 2026-08-17
**Preferred Option:** (a) — atomic slot-reservation port.
**Notes:** Owner forwarded the external review's fix handoff recommending the reservation-port direction ("resolve issue 8003's product decision. The recommended direction is an atomic slot-reservation port…"), matching the issue's own recommendation.

---

## Resolution

**Resolved:** 2026-08-18
**Commit/PR:** branch `fix/8002-multi-terminal-enforcement-never-wired`, merged to `main`

### The design that shipped

**`ShiftSlotReservationInterface`** (Domain\Service) is the uniqueness authority: `reserveForOpen` (all-or-nothing claim of the terminal and cashier slots, refusing a shift id that already holds slots), `prepareTransfer` / `commitTransfer` / `abortTransfer` (moving a shift's cashier), `releaseShift` (idempotent), `reconcile` (recovery). The occupancy RULES stay in `MultiTerminalEnforcementService`; the bookkeeping shared by every implementation lives in `ShiftSlotBook`; implementations supply storage and atomicity — `InMemoryShiftSlotReservation` (single-process reference) and the demo's `FileShiftSlotReservation` (sidecar-file lock, re-read under `LOCK_EX` per mutation, so it is genuinely cross-process).

**A slot is only given up once the aggregate change is durable.** Moving a cashier runs prepare → store → commit: the outgoing operator keeps their slot for the whole in-flight window, so nobody can pick up a second shift on the strength of a change that has not committed, and an abort restores a state that was never left. A shift with a transfer in flight refuses a second one, whoever it targets.

**Optimistic concurrency backs it.** Every shift command whose decision depends on state it read (open — against version 0 — assign, unassign, close, force-close, cash drop) stores against the version it read; `ShiftRepositoryInterface` documents that the guarantee is only as strong as the store behind it. The demo's `FileEventStore` enforces the same rule where it is actually visible: inside its write lock, against the current file, refusing an event whose version is already taken.

**Failures are surfaced, not swallowed.** A cleanup that fails after a command persisted raises `SlotCleanupFailedException`, naming the failure and the recovery step while preserving the original as the cause. `reconcile()` (demo: `./demo shift reconcile`) rebuilds slots from the committed open shifts; it refuses a history that already breaks the invariant, or one that cannot be ordered, rather than reporting a confident correction. Seeding, which runs at every bootstrap, stays permissive — a recovery tool that cannot start is not a recovery tool.

**Residual boundaries, documented rather than engineered away:** the slots and the event store are two stores, so a host wanting single-transaction semantics implements the port inside its own unit of work — what the protocol guarantees without one is that every reachable intermediate state over-blocks rather than over-permits, and is recoverable by reconciliation. The bundled in-memory implementation is single-process only. Resetting the demo while another command is mid-flight is unsupported. `assertOrderBelongsToTerminal` is still unwired, pending an order→terminal read model.

### How it got here — five review rounds

Recorded because the pattern matters more than any single fix: **four of the five defects were created by the previous round's fix**, and each time the flaw was a CHECK where a CLAIM was needed, or a justification that the code contradicted.

1. **Read-model enforcement** (superseded). The first implementation asserted the rules against projection maps inside `OpenShiftHandler`. A projection can never be a concurrency authority — this is what issue 8003 was filed about.
2. **Reservation port** (superseded in part). Check-then-store replaced with an atomic port. Its compensating undo (`transferCashier` / `compensateTransfer`, both since removed) could overwrite a newer command's committed reservation.
3. **Compare-and-swap compensation** (superseded). Made the undo conditional, but it still could not restore a previous holder who had reused the slot released before the store committed — the reason slots are now held until the change is durable (prepare/commit/abort).
4. **Guard ordering and an existence check** (kept, but not sufficient alone). Both were checks where a claim was needed: running the same two commands in the opposite order reproduced both bugs. Both are still live and load-bearing — the in-flight guard and the shift-id refusal each still fire — but what makes them safe under concurrency is the optimistic-concurrency guard above.
5. **The version guard's own regressions.** Applied only at handler level it was inert cross-process, and it turned a harmless race into a permanent wedge (two events claiming one version, which no replay can order and no retry can clear, while reconciliation called it healthy). Enforcement moved into the event store's write lock; a history already in that state is now reported as malformed, naming the aggregate and the remedy. A sixth round then caught the first attempt at containing it: hiding those streams from the projection made two strict guards permissive (a shift closed with an active session; an occupied terminal seeded as free), because both guards are answered from that projection. The projection now sees everything and the COMMANDS refuse instead — shift and session commands while any stream is unorderable, `reconcile` included — so the failure direction stays "over-block", never "over-permit".

### Validation

Unit: `InMemoryShiftSlotReservationTest` (in-flight/abort/commit semantics, duplicate shift id, post-close transfer, reconciliation), `InMemoryShiftReadModelTest` (the projection's operator rule), `FileShiftSlotReservationTest` (permissive seed vs refusing reconcile, corrupt in-flight list, legacy file), `FileEventStoreTest` (version refusal, all-or-nothing multi-event append, malformed history reported as such and still offered to the projection), `DemoResetTest` (three-store reset rollback). Handler level: deterministic interleavings via `InterleavingShiftRepository` and `CallbackFailingShiftRepository`, hooking both load and store. Integration: `DemoCliShiftOpenRaceTest` (two concurrent CLI opens, same terminal and same cashier) and `DemoCliMalformedHistoryTest` (an unorderable history refuses shift and session commands — including a close whose active-session guard reads a corrupt SESSION stream — never quietly frees the terminal, and leaves the remedy reachable). Plus manual runs of real concurrent demo processes for racing cash drops, assigns, unassigns and closes — exactly one winner each, stream well-formed, shift still operable afterwards (a shift whose history is already unorderable is not operable at all — the remedy there is `state clear`).
