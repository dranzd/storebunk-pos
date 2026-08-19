# 8002 — MultiTerminalEnforcementService Is Never Wired Into Any Handler

**Type:** Missing Feature
**Status:** Resolved
**Severity:** High
**Reported:** 2026-08-17
**Resolved:** 2026-08-17
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

**Decision:** Accept
**Date answered:** 2026-08-17
**Preferred Option / Question Answers:**

- **Q1 — (a) Option A.** Owner said "do that" after the plain-language walkthrough of the issue: complete the wiring so the library enforces its documented invariants.
- **Q2 — (a) Keep High.** No override given.

**Notes:** Owner's standing note from triage: no code modification unless the issue affects the core or the design itself — this one does (an unenforced documented invariant), so implementation proceeded.

---

## Resolution

**Resolved:** 2026-08-17
**Commit/PR:** branch `fix/8002-multi-terminal-enforcement-never-wired`
**Summary:** the invariants are enforced through `ShiftSlotReservationInterface` → `ShiftSlotBook` → `MultiTerminalEnforcementService`: the five slot-holding shift handlers (open, assign, unassign, close, force-close) claim, move or release slots through the reservation port, which is the atomicity boundary; a cash drop holds no slot. `InMemoryShiftReadModel` (new, `src/Infrastructure/Shift/ReadModel/`) projects `ShiftOpened`/`ShiftAssigned`/`ShiftUnassigned`/`ShiftClosed`/`ShiftForceClosed` and stays QUERY state — the committed authority that slot seeding and reconciliation compare against, never the enforcement path. `OpenShiftHandler` additionally refuses a shift id that already exists, so a closed shift cannot be reopened onto its old stream (which would inherit the previous life's assignee and cash drops). Demo bootstrap projects the shift read model in its replay loop and wires those five handlers with the reservation. Covered by `OpenShiftHandlerTest` (refusal on occupied terminal, refusal for busy cashier, refusal to reuse an open or closed shift id, independent terminals unaffected), `InMemoryShiftReadModelTest` (the projection's operator rule), and verified end-to-end in the demo CLI (second `shift open` on the same terminal exits 1 with "already has an open shift"). `assertOrderBelongsToTerminal` remains uncalled, and on review (2026-08-19) that is the right end state rather than pending work. The binding it checks is structural for REACHING an order: resume, reactivate and sync validate the id against their session's own parked / inactive / pending-sync lists, complete/cancel/park take no id at all, and a session is bound to one terminal at start — so an order held by one session cannot be operated from another. It is NOT structural for CLAIMING an id: `StartNewOrder` accepts one from the caller, and the aggregate can only refuse an id that session has already used (added 2026-08-19). Two sessions being handed the same id is a host concern — order ids belong to the Ordering context — and this method is the check a host makes there. Building the order→terminal read model once imagined for this would create a second home for the rule that could drift from the aggregate, which is precisely the failure mode issue 8003 spent five review rounds proving out. The guarantee is pinned by `OrderTerminalBindingTest` (another terminal's session cannot resume or reactivate an order it does not hold; an id-less command parks only its own session's order; a session never leaves the terminal it started on; an id already used is refused; the owning session still works), and the method stays as a host-facing utility for callers that address orders outside a session.

> **Superseded within the same branch.** This issue's first implementation asserted the rules against read-model maps (`openShiftsByTerminal()` / `activeTerminalByCashier()`) inside `OpenShiftHandler`. Issue [8003](8003-shift-enforcement-not-atomic.md) showed a projection can never be a concurrency authority and replaced that with the reservation port; those two interface methods no longer exist. Read 8003 for the design that actually shipped.
