# StoreBunk POS - Domain Model Specification

This document defines the complete domain model for the POS Bounded Context: aggregates, value objects, commands, events, queries, policies, and invariants.

---

## 1. Aggregates

### 1.1 Terminal (Entity / Lightweight Aggregate)

Represents a registered POS device.

#### Fields

| Field | Type | Description |
|-------|------|-------------|
| `terminalId` | `TerminalId` (VO) | Unique identifier |
| `branchId` | `BranchId` (VO) | Branch this terminal belongs to |
| `name` | `string` | Human-readable terminal name |
| `status` | `TerminalStatus` (Enum) | Active, Disabled, Maintenance |
| `registeredAt` | `DateTimeImmutable` | When the terminal was registered |

#### Invariants

- Terminal belongs to exactly one branch.
- Terminal can have only one open shift at a time.
- Terminal must be Active to open a shift.

#### Commands

| Command | Description |
|---------|-------------|
| `RegisterTerminal` | Register a new terminal for a branch |
| `ActivateTerminal` | Set terminal status to Active |
| `DisableTerminal` | Set terminal status to Disabled |
| `SetTerminalMaintenance` | Set terminal to Maintenance mode |

#### Events

| Event | Description |
|-------|-------------|
| `TerminalRegistered` | Terminal was registered |
| `TerminalActivated` | Terminal was activated |
| `TerminalDisabled` | Terminal was disabled |
| `TerminalMaintenanceSet` | Terminal entered maintenance mode |

---

### 1.2 Shift (Aggregate Root)

Represents a cashier working session on a terminal. This is the **primary aggregate** for operational accountability.

#### Fields

| Field | Type | Description |
|-------|------|-------------|
| `shiftId` | `ShiftId` (VO) | Unique identifier |
| `terminalId` | `TerminalId` (VO) | Terminal this shift is on |
| `branchId` | `BranchId` (VO) | Branch context |
| `cashierId` | `CashierId` (VO) | Cashier operating this shift |
| `status` | `ShiftStatus` (Enum) | Open, Closed, ForcedClosed |
| `openedAt` | `DateTimeImmutable` | When shift was opened |
| `closedAt` | `?DateTimeImmutable` | When shift was closed (nullable) |
| `openingCashAmount` | `Money` (VO) | Cash in drawer at shift open |
| `declaredClosingCashAmount` | `?Money` (VO) | Cashier-declared cash at close (nullable until close) |
| `expectedCashAmount` | `Money` (VO) | System-calculated expected cash (derived) |
| `varianceAmount` | `Money` (VO) | Difference between declared and expected (derived) |
| `cashDrops` | `CashDrop[]` | List of cash drops during shift |

#### Derived Calculations

```
expectedCash =
    openingCash
  + totalCashPayments
  - totalCashRefunds
  - sum(cashDrops)

variance = declaredClosingCash - expectedCash
```

#### Invariants

1. One cashier = one terminal per open shift.
2. A cashier cannot open multiple shifts simultaneously.
3. A terminal cannot have multiple open shifts.
4. Shift cannot close if Draft orders exist for this shift.
5. Shift cannot close if Confirmed (not Completed) orders exist for this shift.
6. Force close requires supervisor authorization.
7. Force close does NOT auto-cancel Confirmed orders.
8. Cash drops cannot be edited or deleted once recorded.
9. No expense withdrawals allowed.

#### Commands

| Command | Description |
|---------|-------------|
| `OpenShift` | Open a new shift for a cashier on a terminal |
| `CloseShift` | Close the shift with declared cash amount |
| `ForceCloseShift` | Force close with supervisor auth (audit logged) |
| `RecordCashDrop` | Record a cash removal from the drawer |

#### Events

| Event | Description |
|-------|-------------|
| `ShiftOpened` | Shift was opened (includes openingCashAmount) |
| `ShiftClosed` | Shift was closed normally (includes variance) |
| `ShiftForceClosed` | Shift was force-closed by supervisor |
| `CashDropRecorded` | Cash was removed from the drawer |

#### Policies

- **ShiftCloseBlockPolicy**: Before closing, verify no unresolved orders exist.
- **CashVariancePolicy**: On close, compute and record variance. Never silently correct.

---

### 1.3 PosSession (Aggregate Root)

Represents the active UI lifecycle on a terminal during a shift. Manages which order is currently being worked on.

#### Fields

| Field | Type | Description |
|-------|------|-------------|
| `sessionId` | `SessionId` (VO) | Unique identifier |
| `shiftId` | `ShiftId` (VO) | Parent shift |
| `terminalId` | `TerminalId` (VO) | Terminal context |
| `activeOrderId` | `?OrderId` (VO) | Currently active order (nullable) |
| `parkedOrderIds` | `OrderId[]` | Orders parked for later |
| `inactiveOrderIds` | `OrderId[]` | Orders deactivated due to TTL expiry |
| `pendingSyncOrderIds` | `OrderId[]` | Offline orders awaiting sync |
| `state` | `SessionState` (Enum) | Idle, Building, Checkout |

#### Invariants

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

## 2. Value Objects

| Value Object | Context | Description | Base |
|-------------|---------|-------------|------|
| `TerminalId` | Terminal | UUID for terminal | `Uuid` (common-valueobject) |
| `BranchId` | Terminal | UUID for branch | `Uuid` (common-valueobject) |
| `TerminalStatus` | Terminal | Enum: `Active`, `Disabled`, `Maintenance` | PHP Enum |
| `ShiftId` | Shift | UUID for shift | `Uuid` (common-valueobject) |
| `CashierId` | Shift | UUID for cashier | `Uuid` (common-valueobject) |
| `ShiftStatus` | Shift | Enum: `Open`, `Closed`, `ForceClosed` | PHP Enum |
| `CashDrop` | Shift | Cash drop record (amount + timestamp) | Value Object |
| `SessionId` | PosSession | UUID for session | `Uuid` (common-valueobject) |
| `OrderId` | PosSession | UUID for order reference | `Uuid` (common-valueobject) |
| `SessionState` | PosSession | Enum: `Idle`, `Building`, `Checkout` | PHP Enum |
| `OfflineMode` | PosSession | Offline mode flag/metadata | Value Object |
| `Money` | Shared | Monetary amount (amount + currency) | `Money\Basic` (common-valueobject) |

---

## 3. Domain Services

| Service | Location | Description |
|---------|----------|-------------|
| `DraftLifecycleService` | `Domain\Service\` | TTL checks: `shouldDeactivateOrder()` (15 min), `isOrderExpired()` (60 min) |
| `MultiTerminalEnforcementService` | `Domain\Service\` | Enforces one-shift-per-terminal, one-terminal-per-cashier, order-terminal binding |
| `PendingSyncQueue` | `Domain\Service\` | Tracks offline orders awaiting sync; supports idempotency via commandId |
| `OrderingServiceInterface` | `Domain\Service\` | Port: `createDraftOrder(OrderId, DraftOrderContext)`, `confirmOrder`, `cancelOrder`, `isOrderFullyPaid` |
| `InventoryServiceInterface` | `Domain\Service\` | Port: `confirmReservation`, `releaseReservation`, `fulfillOrderReservation`, `attemptReReservation` |
| `PaymentServiceInterface` | `Domain\Service\` | Port: `requestPaymentAuthorization`, `applyPayment` |
| `ShiftClosePolicy` | `Domain\Service\` | Enforces invariant: shift cannot close if active POS sessions exist |
| `DraftOrderContext` | `Domain\Service\` | DTO carrying `branchId` and optional `customerId` for draft order creation |

---

## 4. Enums

| Enum | Values | Description |
|------|--------|-------------|
| `TerminalStatus` | `Active`, `Disabled`, `Maintenance` | Terminal lifecycle states |
| `ShiftStatus` | `Open`, `Closed`, `ForceClosed` | Shift lifecycle states |
| `SessionState` | `Idle`, `Building`, `Checkout` | Session UI lifecycle |

---

## 5. Order Lifecycle in POS Context

POS interacts with SalesOrder states managed by the Ordering BC. POS does NOT own the order aggregate — it orchestrates transitions.

### Order Phases (from POS perspective)

| Phase | Reservation | TTL | Editable | Auto-Expire |
|-------|------------|-----|----------|-------------|
| **Draft (Active)** | Soft | Yes | Yes | Yes (inactivity TTL) |
| **Inactive** | Released | N/A | No (must resume) | Yes (1 hour → Cancelled) |
| **Confirmed** | Hard | No | No | Never |
| **Completed** | Deducted | N/A | No | N/A |
| **Cancelled** | Released | N/A | No | N/A |

### Draft Phase (Soft Commitment)

- Created when cashier starts a new order
- Items added directly to SalesOrder via Ordering BC
- Inventory reservation = SOFT (TTL enforced by Inventory BC)
- Draft inactivity TTL enforced
- If TTL expires → reservation released → order transitions to Inactive

### Inactive Phase

- Can be resumed ONLY by same terminal + same shift
- Resume attempts re-reserve all items atomically
- If insufficient stock → alert cashier, do not partially resume
- If inactive > 1 hour → auto-cancel

### Checkout Phase (Hard Commitment)

- Triggered when cashier presses "Checkout"
- Order transitions Draft → Confirmed
- Reservation becomes HARD (no TTL)
- Cart modifications disabled
- Explicit cancel required to reverse

### Confirmed / Partially Paid

- No TTL, reservation persists
- Order independent of session
- Allowed: apply additional payment, process return (future)
- Not allowed: edit lines, edit price, edit quantity
- Shift close must block if Confirmed but not Completed orders exist

---

## 5. Payment Orchestration

POS is a thin orchestrator for payments:

```
1. POS → Payment BC (request authorization)
2. Payment BC → OK / NOT OK
3. If OK → POS instructs SalesOrder.applyPayment()
4. If fully paid → POS triggers complete()
```

POS does NOT:
- Persist payment authorization logic
- Retry payment internally
- Handle gateway logic

Manual intervention allowed if failure scenario occurs.

---

## 6. Inventory Reservation Model (POS Perspective)

| Order Phase | Reservation Type | Managed By |
|-------------|-----------------|------------|
| Draft | Soft (TTL) | Inventory BC |
| Checkout/Confirmed | Hard (no TTL) | Inventory BC |
| Completed | Deducted from onHand | Inventory BC |
| Cancelled | Released | Inventory BC |

POS never manipulates stock directly. It requests reservation operations through `InventoryServiceInterface`.

---

## 7. Cash Handling Model

### Allowed Cash Movements

| Movement | Effect on Drawer | Source |
|----------|-----------------|--------|
| Opening cash | Sets initial amount | Shift open |
| Cash payment | Increases drawer | Order payment |
| Cash refund | Decreases drawer | Order refund |
| Cash drop | Decreases drawer | Manual removal |
| Closing declaration | Recorded for variance | Shift close |

### Cash Drop Fields

| Field | Type | Description |
|-------|------|-------------|
| `dropId` | `string` | Unique identifier |
| `shiftId` | `ShiftId` | Parent shift |
| `amount` | `Money` | Amount removed |
| `performedBy` | `CashierId` | Who performed the drop |
| `approvedBy` | `?string` | Supervisor who approved (if required) |
| `reason` | `string` | Reason for the drop |
| `timestamp` | `DateTimeImmutable` | When the drop occurred |

### Rules

- Cash drops are **immutable** — cannot be edited or deleted
- No expense withdrawals allowed in POS
- Variance is recorded, never silently corrected

---

## 8. Offline Behavior

| Allowed Offline | Not Allowed Offline |
|----------------|-------------------|
| Draft creation | Card payments |
| Item scanning | External payment required |
| Cash-only completion (feature flag) | |

Offline orders marked as `PendingSync`. Upon reconnection, replay commands with idempotency keys.

---

## 9. Concurrency & Versioning

- All aggregates use **optimistic versioning**
- Commands must include unique `commandId` for idempotency
- Resume re-reservation must be **atomic** (all-or-nothing)
- Reservation expiration handled **server-side only**

---

## 10. Domain Service Interfaces (Ports)

### OrderingServiceInterface

```php
interface OrderingServiceInterface
{
    public function createDraftOrder(OrderId $orderId, DraftOrderContext $context): void;
    public function confirmOrder(OrderId $orderId): void;
    public function cancelOrder(OrderId $orderId, string $reason): void;
    public function isOrderFullyPaid(OrderId $orderId): bool;
}
```

### InventoryServiceInterface

```php
interface InventoryServiceInterface
{
    public function confirmReservation(OrderId $orderId): void;
    public function releaseReservation(OrderId $orderId): void;
    public function fulfillOrderReservation(OrderId $orderId): void;
    public function attemptReReservation(OrderId $orderId): bool;
}
```

### PaymentServiceInterface

```php
interface PaymentServiceInterface
{
    public function requestPaymentAuthorization(OrderId $orderId, Money $amount, string $paymentMethod): bool;
    public function applyPayment(OrderId $orderId, Money $amount, string $paymentMethod): void;
}
```

---

## 11. Complete Invariants Summary

| # | Invariant | Enforced By |
|---|-----------|-------------|
| 1 | One cashier = one terminal per open shift | Shift aggregate |
| 2 | One terminal = one open shift | Shift aggregate |
| 3 | Shift cannot close if Draft or Confirmed orders exist | ShiftCloseBlockPolicy |
| 4 | Checkout locks order lines | PosSession + Ordering BC |
| 5 | Payment cannot apply without Confirmed state | PosSession |
| 6 | Reservation TTL only applies in Draft | Inventory BC |
| 7 | Confirmed orders cannot auto-expire | Inventory BC |
| 8 | Cash drawer only affected by defined cash movements | Shift aggregate |
| 9 | No expense withdrawal in POS | Shift aggregate |
| 10 | POS never owns pricing, tax, stock deduction, or ledger logic | Architecture boundary |

---

## 12. Event Catalog

### Terminal Events

| Event Name | Key Payload |
|------------|-------------|
| `TerminalRegistered` | terminalId, branchId, name |
| `TerminalActivated` | terminalId |
| `TerminalDisabled` | terminalId |
| `TerminalMaintenanceSet` | terminalId |

### Shift Events

| Event Name | Key Payload |
|------------|-------------|
| `ShiftOpened` | shiftId, terminalId, branchId, cashierId, openingCashAmount |
| `ShiftClosed` | shiftId, declaredCash, expectedCash, variance |
| `ShiftForceClosed` | shiftId, supervisorId, reason |
| `CashDropRecorded` | shiftId, dropId, amount, performedBy, reason |

### Session Events

| Event Name | Key Payload |
|------------|-------------|
| `SessionStarted` | sessionId, shiftId, terminalId |
| `NewOrderStarted` | sessionId, orderId |
| `OrderParked` | sessionId, orderId |
| `OrderResumed` | sessionId, orderId |
| `CheckoutInitiated` | sessionId, orderId |
| `PaymentRequested` | sessionId, orderId, amount, method |
| `OrderCompleted` | sessionId, orderId |
| `OrderCancelledViaPOS` | sessionId, orderId, reason |
| `SessionEnded` | sessionId |

---

## 13. Out of Scope (Future Extensions)

- Return workflows
- Exchange workflows
- Refund without return
- Cross-terminal order reopening
- Layaway policies
- Financial reconciliation
- Expense withdrawal
- Promotion override workflows

The architecture supports these extensions, but they are not part of the foundational scope.
