# 2002 — Application-Layer Handlers Have No Direct Test Coverage

**Type:** Improvement
**Status:** Resolved
**Severity:** Medium
**Reported:** 2026-08-17
**Resolved:** 2026-08-17
**Affects:** src/Application/Terminal/Command/Handler/*, src/Application/Shift/Command/Handler/ForceCloseShiftHandler.php, src/Application/Shift/Command/Handler/RecordCashDropHandler.php, src/Application/PosSession/Command/Handler/{ParkOrderHandler,ResumeOrderHandler,EndSessionHandler}.php, tests/Unit/Demo/FileEventStoreTest.php

---

## Issue

A band of application-layer command handlers has zero direct handler tests: wiring mistakes there (wrong aggregate method called, events not stored, guard skipped) would pass the suite silently. Bundled in: one persistence-layer assertion gap in the demo event-store tests.

---

## Findings

- **Terminal handlers — no direct tests for any of the eight:** `ActivateTerminal`, `DecommissionTerminal`, `DisableTerminal`, `ReassignTerminal`, `RecommissionTerminal`, `RegisterTerminal`, `RenameTerminal`, `SetTerminalMaintenance`. Terminal behavior is covered only at the aggregate-unit level; the handler → repository → store path is untested.
- **Shift/session handlers with the same gap:** `ForceCloseShift`, `RecordCashDrop`, `ParkOrder`, `ResumeOrder`, `EndSession`. (Park/resume/end are exercised indirectly by integration flows, but no test targets the handler contract itself.)
- **Why it matters here specifically:** issue 4001 (handlers drained the event buffer before `store()`, so events were never persisted) was exactly the class of wiring bug direct handler tests catch — and it shipped.
- **Persistence assertion gap (folded in):** `tests/Unit/Demo/FileEventStoreTest.php`'s reload round-trip does not assert `getAggregateRootVersion()` / `loadEventsFromVersion()` ordering survives persistence — a vendor serialization change could silently break version numbering.
- Verified on `main` at `eb77e16` during the 2026-08-16 finalize-local thorough review (round 2); pre-existing, not introduced by the alignment branch.

---

## Root Cause

Handler tests were written where an early bug forced them (PosSession core flow after 4001) and skipped where the handler looked like a trivial pass-through — but "trivial pass-through" is precisely the code whose only failure mode is mis-wiring, which aggregate-level tests cannot see.

---

## Recommended Action

Add one direct handler test per listed command following the existing PosSession handler-test pattern (in-memory store + repository, dispatch command, assert events persisted and state transitioned; assert the guard path throws where a guard exists). Extend the `FileEventStoreTest` round-trip to assert aggregate-root version numbering and `loadEventsFromVersion()` slicing after reload. Test-only change — no production code should need modification.

Files: new tests under `tests/Unit/Application/Terminal/`, additions to existing Shift/PosSession handler test classes, `tests/Unit/Demo/FileEventStoreTest.php`.

---

## Owner Response

**Decision:** Accept
**Date answered:** 2026-08-17
**Notes:** Owner batch-approved implementation of all open triaged issues ("do all so we can be done with this"). Test-only change, as recommended.

---

## Resolution

**Resolved:** 2026-08-17
**Commit/PR:** branch `fix/8002-multi-terminal-enforcement-never-wired`
**Summary:** 33 new tests (suite 257 → 290, assertions 653 → 718). Direct handler tests added for all 13 listed handlers: 8 Terminal handler classes under `tests/Unit/Application/Terminal/Handler/` (happy path asserting the persisted event plus the aggregate's real guards — decommissioned blocks activate/disable/maintenance/rename/reassign, active blocks reassign/decommission, same-name/same-branch refusals, recommission-only-from-decommissioned), `ForceCloseShiftHandlerTest`, `RecordCashDropHandlerTest`, `ParkOrderHandlerTest`, `ResumeOrderHandlerTest`, `EndSessionHandlerTest`. `FileEventStoreTest` gained `test_version_numbering_survives_a_reload_round_trip` asserting `getAggregateRootVersion()` and `loadEventsFromVersion()` slicing/ordering after reload.
