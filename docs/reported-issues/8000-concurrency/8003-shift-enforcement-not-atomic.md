# 8003 — Shift Enforcement Is Check-Then-Store, Not Atomic Under Concurrency

**Type:** Architecture
**Status:** Open
**Severity:** High
**Reported:** 2026-08-17
**Resolved:**
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

> _(Owner fills in this section before implementation begins)_

**Decision:** Accept | Reject | Defer | Needs Discussion
**Preferred Option:**
**Notes:**

---

## Resolution

_(Filled in when resolved)_

**Resolved:**
**Commit/PR:**
**Summary:**
