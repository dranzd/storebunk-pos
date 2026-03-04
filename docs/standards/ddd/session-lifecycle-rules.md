<!-- hash: dd4f7d7de4fdd0bf90d3535942821862c2725dab7d2c1c585b8032fa9b618bfe -->
# session-lifecycle-rules

Category: ddd
Status: stable
Source: storebunk-pos

---

PosSession aggregate must enforce UI session invariants and order reference management. Sessions orchestrate order lifecycle but never own order data directly.

1. Session exists only while shift is open.
2. Session does NOT own order data — only references `orderId`.
3. Session ends when shift closes.
4. Only one active order at a time per session.
5. Parked orders must belong to the same shift.

#### Commands

| Command | Description |
|---------|-------------|
| `StartSession` | Start a new session for a shift |
| `StartNewOrder` | Create a new draft order and set as active |
| `ParkOrder` | Move active order to parked list |
| `ResumeOrder` | Move a parked order to active |
| `ReactivateOrder` | Resume an inactive (TTL-expired) order after re-reservation |
| `DeactivateOrder` | Deactivate the active order due to TTL expiry |
| `InitiateCheckout` | Transition session to Checkout state |
| `RequestPayment` | Request payment from Payment BC |
| `CompleteOrder` | Mark order as completed |
| `CancelOrder` | Cancel the active order |
| `EndSession` | End the session (shift closing) |
| `StartNewOrderOffline` | Create a draft order while offline (queued for sync) |
| `SyncOrderOnline` | Sync an offline-created order to Ordering BC on reconnect |

#### Events

| Event | Description |
|-------|-------------|
| `SessionStarted` | Session was started |
| `NewOrderStarted` | A new draft order was created |
| `OrderParked` | Active order was parked |
| `OrderResumed` | A parked order was resumed |
| `OrderDeactivated` | Active order was deactivated due to TTL expiry |
| `OrderReactivated` | An inactive order was reactivated after successful re-reservation |
| `CheckoutInitiated` | Checkout was triggered (Draft → Confirmed) |
| `PaymentRequested` | Payment was requested from Payment BC |
| `OrderCompleted` | Order was fully paid and completed |
| `OrderCancelledViaPOS` | Order was cancelled from POS |
| `SessionEnded` | Session was ended |
| `OrderCreatedOffline` | Draft order created while offline (carries commandId for idempotency) |
| `OrderMarkedPendingSync` | Offline order queued for sync on reconnection |
| `OrderSyncedOnline` | Offline order successfully synced to Ordering BC |

---

---

## Source File
docs/domain-model.md
