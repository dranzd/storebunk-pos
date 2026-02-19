# 9001 — Offline events use `get`-prefixed accessors, inconsistent with convention

**Type:** Improvement
**Status:** Open
**Severity:** Low
**Reported:** 2026-02-19
**Resolved:**
**Affects:**
- `src/Domain/Model/PosSession/Event/OrderCreatedOffline.php`
- `src/Domain/Model/PosSession/Event/OrderMarkedPendingSync.php`
- `src/Domain/Model/PosSession/Event/OrderSyncedOnline.php`
- `src/Domain/Model/PosSession/PosSession.php` (apply methods)
- `src/Application/PosSession/Command/Handler/StartNewOrderOfflineHandler.php`
- `tests/Unit/Domain/Model/PosSession/PosSessionOfflineTest.php`

---

## Issue

`OrderCreatedOffline` event uses `getSessionId()`/`getOrderId()` accessor naming (prefixed with `get`), inconsistent with all other POS events which use `sessionId()`/`orderId()` (no prefix). Suggest standardizing all event accessors to the no-prefix convention.

---

## Findings

All 14 PosSession event classes were audited. The no-prefix convention (`sessionId()`, `orderId()`) is used consistently across 11 events:

`NewOrderStarted`, `CheckoutInitiated`, `OrderCompleted`, `OrderCancelledViaPOS`, `OrderParked`, `OrderResumed`, `OrderReactivated`, `OrderDeactivated`, `SessionStarted`, `SessionEnded`, `PaymentRequested`

**Three events use `get`-prefixed accessors** — all Phase 8 offline-sync events added later:

| Class | Affected Methods |
|-------|-----------------|
| `OrderCreatedOffline` | `getSessionId()`, `getOrderId()`, `getCommandId()` |
| `OrderMarkedPendingSync` | `getSessionId()`, `getOrderId()` |
| `OrderSyncedOnline` | `getSessionId()`, `getOrderId()` |

The `get`-prefixed methods propagate into internal call sites:

- `PosSession::applyOnOrderCreatedOffline()` (line 403) calls `$event->getOrderId()`
- `PosSession::applyOnOrderMarkedPendingSync()` (line 409) calls `$event->getOrderId()`
- `StartNewOrderOfflineHandler` (lines 29, 33, 34, 38, 39) calls `$command->getSessionId()` and `$command->getOrderId()` — note these are on the **command** (`StartNewOrderOffline`), not the event, and commands in this project inherit `get`-prefixed accessors from `AbstractCommand`. The command naming is a separate question and is consistent with the command layer convention.

The test at `PosSessionOfflineTest.php:46` calls `$event->getCommandId()` and must also be updated.

---

## Root Cause

The three offline-sync event classes were implemented in Phase 8 after the naming convention was established for all other events. The convention was not applied consistently when these later events were added.

---

## Recommended Action

Rename all `get`-prefixed accessors on the three event classes to the no-prefix convention:

| Old | New |
|-----|-----|
| `getSessionId()` | `sessionId()` |
| `getOrderId()` | `orderId()` |
| `getCommandId()` | `commandId()` |

Update all internal call sites:

- `PosSession::applyOnOrderCreatedOffline()` — `getOrderId()` → `orderId()`
- `PosSession::applyOnOrderMarkedPendingSync()` — `getOrderId()` → `orderId()`
- `PosSessionOfflineTest.php:46` — `getCommandId()` → `commandId()`

**Note:** `StartNewOrderOfflineHandler` calls `$command->getSessionId()` and `$command->getOrderId()` — these are on the `StartNewOrderOffline` **command**, not the event. Commands inherit from `AbstractCommand` which uses `get`-prefixed accessors. This is a separate convention and is not in scope for this issue.

---

## Owner Response

**Decision:** Standardize on no-prefix accessors for both events and commands (e.g. `sessionId()`, `orderId()`). No `get` prefix anywhere.
**Preferred Option:** Recommended action
**Notes:** This confirms the existing codebase convention. The three offline events were the outliers.

---

## Resolution

**Resolved:** 2026-02-20
**Commit/PR:** fix/9001-event-accessor-naming
**Summary:** Removed `get`-prefix from all three offline event classes (`OrderCreatedOffline`, `OrderMarkedPendingSync`, `OrderSyncedOnline`) and their command counterparts (`StartNewOrderOffline`, `SyncOrderOnline`). Updated all call sites in `PosSession.php`, `StartNewOrderOfflineHandler`, `SyncOrderOnlineHandler`, and `PosSessionOfflineTest`. All 121 tests pass.
