# 8002 — MultiTerminalEnforcementService Is Never Wired Into Any Handler

**Type:** Missing Feature
**Status:** Open
**Severity:** High
**Reported:** 2026-08-17
**Resolved:**
**Affects:** src/Domain/Service/MultiTerminalEnforcementService.php, src/Application/Shift/ReadModel/ShiftReadModelInterface.php, src/Application/Shift/Command/Handler/OpenShiftHandler.php, src/Application/PosSession/Command/Handler/StartSessionHandler.php

---

## Issue

The multi-terminal invariants documented in `docs/domain-model.md` — one terminal has at most one open shift, one cashier has at most one open shift — are not actually enforced anywhere. The service that implements them exists but is dead code: no handler calls it, and the read model it depends on has no implementation. Two `OpenShift` commands for the same terminal (or the same cashier on two terminals) both succeed.

---

## Findings

- `src/Domain/Service/MultiTerminalEnforcementService.php:18` (`assertTerminalHasNoOpenShift`), `:35` (`assertCashierHasNoOpenShift`), `:52` (`assertOrderBelongsToTerminal`) — grep across `src/` and `demo/` finds no caller of any of these methods; the class is referenced only by its own file and its unit test.
- `src/Application/Shift/ReadModel/ShiftReadModelInterface.php:7` declares `getShift` / `getOpenShifts` / `getShiftsByTerminal`, but no class in `src/` or `demo/` implements the interface — so even a wired-up handler would have nothing to inject.
- `OpenShiftHandler` and `StartSessionHandler` perform no cross-aggregate checks; each loads/creates only its own aggregate.
- Issue **8001** (Resolved) made this service stateless — assert methods now take read-model-sourced state as arguments — but the wiring step never happened. 8001 fixed *how* the service would hold state; this issue is that it was never invoked at all.
- Verified on `main` at `eb77e16` during the 2026-08-16 finalize-local thorough review (round 2); identical before the alignment branch.

---

## Root Cause

Issue 8001's resolution redesigned the enforcement service's shape (stateless, arguments-in) but stopped at the service boundary: the follow-through — implementing a `ShiftReadModelInterface` projection and calling the assert methods from the shift/session handlers — was never scheduled, and nothing failed because no test asserts the invariant end-to-end.

---

## Recommended Action

**Option A (preferred):** Wire enforcement into the application layer.
1. Provide an event-sourced projection implementing `ShiftReadModelInterface` (an in-memory reference implementation in the library, mirroring `InMemoryPosSessionReadModel`).
2. Inject the read model + `MultiTerminalEnforcementService` into `OpenShiftHandler` (assert terminal AND cashier have no open shift) and evaluate whether `StartSessionHandler` needs `assertOrderBelongsToTerminal`.
3. Add handler-level tests: second `OpenShift` on the same terminal refused; same cashier on a second terminal refused.
4. Register the projection in `demo/bootstrap.php` so the demo enforces it too.

**Option B:** Declare the invariant consumer-owned — delete the service, remove the invariant claims from `docs/domain-model.md`, and document that host applications must enforce it. (Rejecting the design rather than completing it.)

Files to change (Option A): the two handlers, a new `InMemoryShiftReadModel`, `demo/bootstrap.php`, tests.

---

## Open Questions for Owner

**Q1. Complete the wiring or hand the invariant to consumers?**
- **(a)** Option A — implement the read model and wire the handlers; the library enforces its documented invariants. **Recommended.**
- **(b)** Option B — remove the dead service and re-document the invariant as consumer-owned.
- **(c)** Defer — keep as filed, decide when shift work next comes up.

**Q2. Severity check.** The severity table reads "invariants unenforced" as Critical; filed as High because the library is pre-integration (no production callers yet).
- **(a)** Keep High. **Recommended.**
- **(b)** Raise to Critical.

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
