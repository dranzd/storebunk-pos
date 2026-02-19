# 9002 — `DeactivateOrder` CQRS command and handler are missing

**Type:** Missing Feature
**Status:** Resolved
**Severity:** High
**Reported:** 2026-02-19
**Resolved:** 2026-02-19
**Affects:**
- `src/Application/PosSession/Command/` — `DeactivateOrder.php` does not exist
- `src/Application/PosSession/Command/Handler/` — `DeactivateOrderHandler.php` does not exist
- `src/Domain/Service/DraftLifecycleService.php` — `checkAndDeactivateInactiveOrders()` is an empty stub

---

## Issue

`DeactivateOrder` CQRS command is referenced in the spec (Phase 8 — Draft TTL, 15-min inactivity deactivation → `OrderDeactivated` event) but does not exist in the library. Without this command, the 15-min soft-deactivation step cannot be implemented — only the 60-min hard-cancel via `CancelOrder` is possible.

---

## Findings

**What already exists (domain layer is complete):**

- `PosSession::deactivateOrder(string $reason)` at `src/Domain/Model/PosSession/PosSession.php:193` — fully implemented, records `OrderDeactivated` and transitions state correctly
- `OrderDeactivated` event at `src/Domain/Model/PosSession/Event/OrderDeactivated.php` — fully implemented with correct no-prefix accessors (`sessionId()`, `orderId()`, `reason()`)
- `PosSession::applyOnOrderDeactivated()` at line 383 — moves order to `$inactiveOrderIds`, clears `$activeOrderId`, sets state to `Idle`
- `DraftLifecycleService::shouldDeactivateOrder()` at `src/Domain/Service/DraftLifecycleService.php:36` — correctly computes the 15-minute inactivity threshold

**What is missing (application layer):**

- `src/Application/PosSession/Command/DeactivateOrder.php` — the CQRS command class does not exist
- `src/Application/PosSession/Command/Handler/DeactivateOrderHandler.php` — the handler does not exist
- `DraftLifecycleService::checkAndDeactivateInactiveOrders()` at line 22 is an **empty stub body** — it does nothing:

```php
public function checkAndDeactivateInactiveOrders(DateTimeImmutable $currentTime): void
{
}
```

The `MysqlSessionProjection` (consumer-side) already handles `OrderDeactivated` events, so the infrastructure is ready to receive the event — it just can never be emitted because the command to trigger it does not exist.

---

## Root Cause

The domain aggregate method and event were implemented, but the application layer (command + handler) was never added. `DraftLifecycleService::checkAndDeactivateInactiveOrders()` was stubbed out as a placeholder but never completed, likely because the command it would need to dispatch did not exist. The two gaps reinforce each other.

---

## Recommended Action

Add the missing application layer components:

**1. Create `src/Application/PosSession/Command/DeactivateOrder.php`**
Follow the same pattern as `CancelOrder.php`. Requires `SessionId` and a `string $reason`.

**2. Create `src/Application/PosSession/Command/Handler/DeactivateOrderHandler.php`**
Follow the same pattern as `CancelOrderHandler.php`:
- Load session via `PosSessionRepositoryInterface`
- Call `$session->deactivateOrder($command->reason())`
- Store session

No BC port calls are needed — deactivation is a POS-internal state transition. The inventory reservation is not released at deactivation (it is released only on cancel or at the 60-min hard-cancel). This matches the design intent: deactivated orders can be reactivated via `ReactivateOrder`.

**3. Implement `DraftLifecycleService::checkAndDeactivateInactiveOrders()`**
This method needs a read model dependency to find sessions with active orders and their last-activity timestamps. Options:
- Inject a `PosSessionReadModelInterface` (or equivalent) that can return sessions with active orders older than the TTL threshold
- For each qualifying session/order, dispatch a `DeactivateOrder` command via the command bus

This requires either injecting a command bus into `DraftLifecycleService` or restructuring it as an application service. The current placement in `Domain/Service/` is questionable if it needs to dispatch commands — consider moving to `Application/`.

Files to create:
- `src/Application/PosSession/Command/DeactivateOrder.php`
- `src/Application/PosSession/Command/Handler/DeactivateOrderHandler.php`

Files to modify:
- `src/Domain/Service/DraftLifecycleService.php` — implement `checkAndDeactivateInactiveOrders()` or move to application layer

---

## Owner Response

> _(Owner fills in this section before implementation begins)_

**Decision:**  Proceed with the recommendation.
**Preferred Option:**
**Notes:**

---

## Resolution

_(Filled in when resolved)_

**Resolved:** 2026-02-19
**Commit/PR:** `hotfix/issues`
**Summary:** Created `DeactivateOrder` command and `DeactivateOrderHandler` following the `CancelOrder`/`CancelOrderHandler` pattern. Created `PosSessionReadModelInterface` with `getSessionsWithActiveOrder()` in `src/Application/PosSession/ReadModel/`. Implemented `DraftLifecycleService::checkAndDeactivateInactiveOrders()` and `checkAndCancelExpiredOrders()` — both inject `PosSessionReadModelInterface` and `CommandBus` (port), query sessions with active orders, and dispatch the appropriate command for each qualifying session. Updated `DraftLifecycleIntegrationTest` to use the new constructor. Added `DeactivateOrderHandlerTest` with 3 cases (happy path, idle-after-deactivation, no-active-order throws).
