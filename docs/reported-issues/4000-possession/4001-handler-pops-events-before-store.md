# 4001 — PosSession handlers drain event buffer before `store()`, events never persisted

**Type:** Bug
**Status:** Resolved
**Severity:** Critical
**Reported:** 2026-02-22
**Resolved:** 2026-02-22
**Affects:**
- `src/Application/PosSession/Command/Handler/CancelOrderHandler.php`
- `src/Application/PosSession/Command/Handler/CompleteOrderHandler.php`
- `src/Application/PosSession/Command/Handler/InitiateCheckoutHandler.php`
- `src/Application/PosSession/Command/Handler/RequestPaymentHandler.php`

---

## Issue

`CancelOrderHandler::__invoke()` calls `$session->popRecordedEvents()` to inspect the `OrderCancelledViaPOS` event for its `orderId`, then calls `$this->sessionRepository->store($session)`. The repository's `store()` also calls `popRecordedEvents()` internally. Because `popRecordedEvents()` drains the buffer destructively, the second call returns an empty array and nothing is written to the event store. The `OrderCancelledViaPOS` event is never persisted, the projection never runs, and the session read model is never updated to `idle`. Cancel always silently succeeds at the command level but has zero effect on domain state.

The same pattern exists in `CompleteOrderHandler`, `InitiateCheckoutHandler`, and `RequestPaymentHandler`, making this a systemic defect across all PosSession handlers that need to inspect events before triggering cross-BC side-effects.

---

## Findings

All four affected handlers follow this sequence:

```php
$session->cancelOrder($command->reason());       // records event into buffer

$events = $session->popRecordedEvents();          // ← drains buffer
$cancelledEvent = end($events);

if ($cancelledEvent instanceof OrderCancelledViaPOS) {
    $this->orderingService->cancelOrder(...);     // side-effects using drained data
    $this->inventoryService->releaseReservation(...);
}

$this->sessionRepository->store($session);        // ← calls popRecordedEvents() again → []
```

`InMemoryPosSessionRepository::store()` at line 38 calls `$session->popRecordedEvents()` and passes the result to `$this->eventStore->appendAll($events)`. When the buffer is already empty, `appendAll` receives `[]` and nothing is stored.

Affected handlers and the events they lose:

| Handler | Event lost |
|---------|-----------|
| `CancelOrderHandler` | `OrderCancelledViaPOS` |
| `CompleteOrderHandler` | `OrderCompleted` |
| `InitiateCheckoutHandler` | `CheckoutInitiated` |
| `RequestPaymentHandler` | `PaymentRequested` |

---

## Root Cause

The handlers need the event's payload (e.g. `orderId`) to trigger cross-BC side-effects, but they obtain it by draining the aggregate's recorded-event buffer via `popRecordedEvents()`. This leaves the buffer empty when `store()` later calls the same method to collect events for persistence. The correct approach is to read the required data from the command or from the aggregate's state before mutation, or to call `store()` first and derive side-effect data from the command input, not from the drained event.

---

## Recommended Action

**Option A (preferred):** Capture the `orderId` from the aggregate's current state (via a dedicated read before mutation) or directly from the command before calling `cancelOrder()`. Call `store()` first, then trigger cross-BC side-effects using the captured data. This eliminates the need to inspect recorded events in the handler entirely.

**Option B:** Peek at the event without draining (requires adding a non-destructive `peekRecordedEvents()` method to the aggregate root in the common library). Not preferred — it adds API surface to the library for a problem that should be solved in the handler.

**Chosen: Option A.** All four handlers are fixed to:
1. Capture the required data (active `orderId`) from the aggregate state before mutation using a dedicated pre-mutation read, or from the command.
2. Call `store()` immediately after the domain method.
3. Trigger cross-BC side-effects after `store()` using the pre-captured data.

Files changed:
- `src/Application/PosSession/Command/Handler/CancelOrderHandler.php`
- `src/Application/PosSession/Command/Handler/CompleteOrderHandler.php`
- `src/Application/PosSession/Command/Handler/InitiateCheckoutHandler.php`
- `src/Application/PosSession/Command/Handler/RequestPaymentHandler.php`

---

## Owner Response

> _(Owner fills in this section before implementation begins)_

**Decision:** Accept
**Preferred Option:** Option A
**Notes:**

---

## Resolution

**Resolved:** 2026-02-22
**Commit/PR:** fix(4001): stop draining event buffer before store() in PosSession handlers
**Summary:** Removed all `popRecordedEvents()` calls from the four PosSession command handlers. Each handler now captures the active order ID from the read model (via a pre-mutation load of the session's current state) before calling the domain method, then calls `store()` immediately, then triggers cross-BC side-effects using the pre-captured data. Events are now correctly persisted on every handler invocation.
