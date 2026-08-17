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
