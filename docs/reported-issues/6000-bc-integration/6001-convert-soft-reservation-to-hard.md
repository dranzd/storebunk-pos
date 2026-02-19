# 6001 — `convertSoftReservationToHard()` has no inventory BC mapping

**Type:** Improvement
**Status:** Resolved
**Severity:** Medium
**Reported:** 2026-02-19
**Resolved:** 2026-02-19
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
The idea that this is a library and is framework agnostic  and  no knowledge of other libraries is
correct and it only needs to know of the concept of reservations.  Now if the name do not match with
the inventory library that the consumer use, this library do not care about that.  But we will rename
our methods or classes that reflects our design intention.  If the inventory library has a different
name for the same concept, it is up to the consumer to map it.

For this issue I think both are correct but I will go with Option A for the reason that we do not
assume what the consumer has.  If it has auto confirmaton on order complete or not we do not know that.
So we will provide a method that can be called to confirm the reservation if the consumer needs it.
We do NEED to make it clear that this is a no-op in the adapter.

**Preferred Option:**
**Notes:**

---

## Resolution

_(Filled in when resolved)_

**Resolved:** 2026-02-19
**Commit/PR:** `hotfix/issues`
**Summary:** Renamed `convertSoftReservationToHard()` to `confirmReservation()` in `InventoryServiceInterface`. Added PHPDoc on the interface explicitly stating the adapter is intentionally a no-op until the inventory BC exposes a matching operation, and that mapping is the consumer's responsibility. Updated `InitiateCheckoutHandler` call site and `StubInventoryService` (renamed method and internal array from `hardReservations` to `confirmedReservations`, renamed `hasHardReservation()` to `hasConfirmedReservation()`).
