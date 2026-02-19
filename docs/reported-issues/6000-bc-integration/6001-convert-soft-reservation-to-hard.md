# 6001 — `convertSoftReservationToHard()` has no inventory BC mapping

**Type:** Improvement  
**Status:** Open  
**Severity:** Medium  
**Reported:** 2026-02-19  
**Resolved:**  
**Affects:**
- `src/Domain/Service/InventoryServiceInterface.php`
- `src/Application/PosSession/Command/Handler/InitiateCheckoutHandler.php`
- `tests/Stub/Service/StubInventoryService.php`

---

## Issue

`InventoryServiceInterface::convertSoftReservationToHard()` has no clear mapping to the current inventory library. There is no explicit "confirm reservation" method on `ReservationManager`. The adapter is a no-op stub until the library exposes this. Suggest adding `ReservationManager::confirmReservation(ReservationId)` or renaming the interface method to better reflect the actual inventory model.

---

## Findings

The interface method is defined at `src/Domain/Service/InventoryServiceInterface.php:11`:

```php
public function convertSoftReservationToHard(OrderId $orderId): void;
```

It is called in `InitiateCheckoutHandler` (line 32) immediately after `CheckoutInitiated` is emitted — the correct semantic moment, as checkout locks the order.

Examining `storebunk-inventory`'s `ReservationManager` (the sole public API of the inventory BC), it exposes:

| Method | Purpose |
|--------|---------|
| `requestReservation()` | Create reservation (allocated or backordered) |
| `releaseReservation()` | Release stock back |
| `cancelReservation()` | Cancel (terminal state) |
| `fulfillReservation()` | Mark as fulfilled (stock consumed) |
| `expireReservations()` | Batch expire overdue |

**There is no `confirmReservation()` or soft→hard promotion method.** The inventory model has no concept of soft vs. hard reservations. A reservation is either `Active`, `Backordered`, `Released`, `Cancelled`, `Fulfilled`, or `Expired`. There is no intermediate "confirmed" state.

The `StubInventoryService` simulates the distinction with internal arrays (`$softReservations`, `$hardReservations`) but this is test-only fiction — it has no counterpart in the real inventory BC.

---

## Root Cause

The POS domain invented a soft/hard reservation distinction that does not exist in the inventory BC. The interface method name encodes an assumption about the inventory model that is incorrect. The adapter cannot implement this method meaningfully until either the inventory BC adds a matching concept, or the POS interface is redesigned to match what the inventory BC actually models.

---

## Recommended Action

**Option A (preferred) — Rename to reflect intent, document no-op:**  
Rename `convertSoftReservationToHard()` to `confirmReservation()`. Add a PHPDoc comment on the interface stating that the adapter is intentionally a no-op until the inventory BC exposes a matching operation. This makes the intent clear without inventing inventory concepts.

**Option B — Remove the method:**  
If the POS domain decides that checkout confirmation is fully handled by `OrderingServiceInterface::confirmOrder()` (already called in the same handler at line 31), remove `convertSoftReservationToHard()` entirely. The inventory BC does not need a separate signal at checkout time.

**Option B is architecturally cleaner** — the inventory reservation was created at draft time and will be fulfilled at order completion. There is no inventory-side action needed at checkout.

Files to change under either option:
- `src/Domain/Service/InventoryServiceInterface.php`
- `src/Application/PosSession/Command/Handler/InitiateCheckoutHandler.php`
- `tests/Stub/Service/StubInventoryService.php`

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
