# 6002 — `deductInventory()` name misleading, mapping unclear

**Type:** Improvement  
**Status:** Open  
**Severity:** Medium  
**Reported:** 2026-02-19  
**Resolved:**  
**Affects:**
- `src/Domain/Service/InventoryServiceInterface.php`
- `src/Application/PosSession/Command/Handler/CompleteOrderHandler.php`
- `tests/Stub/Service/StubInventoryService.php`

---

## Issue

`InventoryServiceInterface::deductInventory()` has no direct mapping — inventory deduction in the current model happens as a side effect of `SalesOrder` line completion, not as an explicit POS command. The adapter is a no-op stub. Suggest clarifying whether POS should own deduction or if it should remain a SalesOrder BC concern.

---

## Findings

The interface method is defined at `src/Domain/Service/InventoryServiceInterface.php:15`:

```php
public function deductInventory(OrderId $orderId): void;
```

It is called in `CompleteOrderHandler` (line 38) after `OrderCompleted` is emitted — after confirming the order is fully paid.

Examining `storebunk-inventory`'s `ReservationManager`, the closest operation is `fulfillReservation(ReservationId)` (line 337). This **does exist** and marks the reservation as `Fulfilled`, which semantically means stock has been consumed. However:

1. **Signature mismatch:** The POS interface takes `OrderId`, not `ReservationId`. The adapter must look up reservation(s) by `ReferenceId` (which maps to `OrderId`) via a read model query before calling `fulfillReservation()`. This is implementable but undocumented.
2. **No explicit stock deduction step exists:** In the inventory BC, stock levels are reduced at reservation time (reserved quantity is tracked separately from available quantity). `fulfillReservation()` transitions reservation state — it does not write to stock levels again. There is no separate "deduct" command.
3. **Method name is misleading:** `deductInventory` implies a direct write to stock levels. The actual operation is a reservation state transition (`Active → Fulfilled`).

The `StubInventoryService` simulates deduction by moving the order from `$hardReservations` to `$deductedInventory`, which is test-only fiction.

---

## Root Cause

The method name encodes an assumption about how inventory deduction works that does not match the inventory BC's model. The operation is semantically correct (POS should signal that goods left the store at order completion), but the name implies a stock-level write that doesn't exist. The adapter is implementable via `fulfillReservation()` with a reference lookup, but this is not documented anywhere.

---

## Recommended Action

**Rename** `deductInventory()` to `fulfillOrderReservation()` to accurately reflect the inventory BC operation. Add a PHPDoc comment on the interface method stating:
- The adapter must resolve `OrderId → ReservationId(s)` via a read model query (e.g., `ReservationManager::getActiveReservationsByReference()`)
- Then call `ReservationManager::fulfillReservation(ReservationId)` for each active reservation

This makes the adapter contract explicit and removes the misleading "deduct" framing.

Files to change:
- `src/Domain/Service/InventoryServiceInterface.php` — rename method
- `src/Application/PosSession/Command/Handler/CompleteOrderHandler.php` — update call site
- `tests/Stub/Service/StubInventoryService.php` — rename method

---

## Owner Response

> _(Owner fills in this section before implementation begins)_

**Decision:**  
**Preferred Option:**  
**Notes:**

---

## Resolution

_(Filled in when resolved)_

**Resolved:**  
**Commit/PR:**  
**Summary:**
