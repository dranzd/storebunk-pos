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

**Resolved:** 2026-08-17
**Commit/PR:** branch `fix/8002-multi-terminal-enforcement-never-wired`
**Summary:** New `ShiftSlotReservationInterface` (Domain\Service): `reserveForOpen` (all-or-nothing claim of terminal + cashier slots), `transferCashier` (atomic move, returns previous holder for compensation), `releaseShift` (idempotent). The occupancy RULES stay in `MultiTerminalEnforcementService`; implementations add slot state and the atomicity boundary. `OpenShiftHandler` reserves before storing and releases on store failure; `CloseShiftHandler`/`ForceCloseShiftHandler` release after storing; `AssignShiftHandler`/`UnassignShiftHandler` transfer the cashier slot (to the assignee / back to the opener via the new `Shift::openedBy()` accessor) and transfer back on store failure. Implementations: `InMemoryShiftSlotReservation` (single-process reference) and demo `FileShiftSlotReservation` (sidecar-file lock, re-reads current slots under `LOCK_EX` per mutation — real cross-process atomicity; seeded from replayed open shifts on first run, cleared by `state clear`). The shift read model is demoted to pure query state (enforcement-map methods removed). Validated by `DemoCliShiftOpenRaceTest` (two concurrent CLI opens for the same terminal, and for the same cashier — exactly one wins in each), reservation unit tests including refusal-leaves-no-partial-claim and transfer semantics, and a handler test proving a failed store releases the slots. Conflicting assignment and opener-restoration guards are covered at handler level (`AssignShiftHandlerTest`, `UnassignShiftHandlerTest`) — those flows share the same atomic transfer primitive the race test exercises cross-process.

**Follow-up hardening (2026-08-18, third review round):** compensation is gone; moving a shift's cashier is now a prepare → store → commit protocol (`prepareTransfer` / `commitTransfer` / `abortTransfer`). A slot is only ever GIVEN UP once the matching aggregate change is durable: the outgoing cashier keeps their slot for the whole in-flight window, so they cannot pick up a second open shift on the strength of a change that may still fail, and an abort restores a state that was never left. That closes the hole compare-and-swap compensation could not: an undo that finds its target has since acquired another slot no longer has to choose between clobbering committed state and leaving an open shift with no cashier slot. A shift with a transfer in flight also refuses a second one, so racing assigns cannot interleave. Cleanup that fails after a command persisted no longer passes silently: handlers raise `SlotCleanupFailedException`, whose message names the failure and the recovery step while `getPrevious()` still carries the original exception — nothing is masked. The recovery step is the new `reconcile()` port method (demo: `./demo shift reconcile`), which rebuilds the slots from the committed open shifts and reports how many entries it corrected; it discards in-flight claims, so it is documented as a maintenance operation. The slot algebra shared by every implementation moved into `ShiftSlotBook`, leaving the implementations to supply only storage and atomicity. Validated by controlled interleavings at handler level — the opener trying to open another shift during a failing assign is refused and the rollback leaves the shift consistent (`AssignShiftHandlerTest`), the same for the assignee during a failing unassign (`UnassignShiftHandlerTest`), a second assign refused mid-flight, a failed slot release after a committed close surfacing as an uncertain-slot-state error (`CloseShiftHandlerTest`) — plus in-flight/abort/commit and reconciliation semantics in `InMemoryShiftSlotReservationTest`. Residual boundary, unchanged: slots and the aggregate store are still two stores, so a host wanting single-transaction semantics implements the port inside its own unit of work; what the protocol guarantees without one is that every reachable intermediate state is SAFE (over-blocking, never over-permitting) and recoverable by reconciliation.

**Follow-up hardening (2026-08-17, second review round):** compensation is compare-and-swap, not unconditional — `compensateTransfer(shiftId, backTo, ifHeldBy)` undoes a transfer only while `ifHeldBy` still holds the slot, so a losing command can never overwrite a newer command's committed reservation (and never steals a slot its target has since acquired). `reserveForOpen` also claims the SHIFT id itself, so two racing opens sharing an id cannot both claim and a loser's release only ever drops its own slots; `transferCashier` refuses a shift with no cashier slot, so a stale assign/unassign cannot recreate slots after a close. Compensation failures never mask the original persistence exception. Demo reset is fully coordinated: `DemoReset::clearAll` takes all three sidecar locks in fixed order (events → state → slots), stages every fallible step, and moves both event history and slot state aside recoverably — a failure at any step restores everything. Validated by controlled-interleaving tests: a losing assign racing a committed winning assign leaves the winner's reservation intact (`AssignShiftHandlerTest`), CAS/no-clobber/no-steal reservation semantics, duplicate-shift-id refusal, post-close transfer refusal (`InMemoryShiftSlotReservationTest`), and reset rollback on slot-clear failure (`DemoResetTest`). Residual boundary, unchanged and documented: slots and the aggregate store remain two stores — a host wanting single-transaction semantics implements the port inside its own unit of work; resetting the demo while another command is mid-flight (past bootstrap) is unsupported.
