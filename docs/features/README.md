# Features Implementation Overview

This directory contains detailed implementation plans for each core feature of the StoreBunk POS library, organized by domain capability.

## Feature Status Legend

- **Not Started** - Planning phase
- **In Progress** - Active development
- **Completed** - Feature implemented and tested
- **Reviewed** - Code reviewed and documented

---

## Core Features

### 1000 Series - Foundation and Shared Kernel

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 1001 | Base Classes | **Completed** | **Critical** | Provided by common libraries; DomainEventInterface marker added |
| 1002 | Event Store Interface | **Completed** | **Critical** | Provided by `dranzd/common-event-sourcing`; `InMemoryEventStore` used via common library |
| 1003 | CQRS Bus Integration | **Completed** | **Critical** | `SimpleCommandBus`, `SimpleQueryBus`, `InMemoryHandlerRegistry` from `dranzd/common-cqrs` |
| 1004 | Shared Value Objects | **Completed** | **Critical** | `BranchId`, `CashierId` implemented; `Money` from `dranzd/common-valueobject` |
| 1005 | Exception Hierarchy | **Completed** | **High** | `DomainException`, `AggregateNotFoundException`, `InvariantViolationException`, `ConcurrencyException` |

### 2000 Series - Terminal Aggregate

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 2001 | Terminal Domain Model | **Completed** | **Critical** | `Terminal` aggregate, `TerminalId`, `TerminalStatus` |
| 2002 | Terminal Commands and Handlers | **Completed** | **Critical** | `RegisterTerminal`, `ActivateTerminal`, `DisableTerminal`, `SetTerminalMaintenance` |
| 2003 | Terminal Events | **Completed** | **Critical** | `TerminalRegistered`, `TerminalActivated`, `TerminalDisabled`, `TerminalMaintenanceSet` |
| 2004 | Terminal Repository and Projection | **Completed** | **High** | `TerminalRepositoryInterface` + `InMemoryTerminalRepository` + `InMemoryTerminalReadModel` |

### 3000 Series - Shift Aggregate

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 3001 | Shift Domain Model | **Completed** | **Critical** | `Shift` aggregate root with full lifecycle |
| 3002 | Shift Value Objects | **Completed** | **Critical** | `ShiftId`, `ShiftStatus`, `CashDrop`; `Money` from common-valueobject |
| 3003 | Shift Commands and Handlers | **Completed** | **Critical** | `OpenShift`, `CloseShift`, `ForceCloseShift`, `RecordCashDrop` |
| 3004 | Shift Events | **Completed** | **Critical** | `ShiftOpened`, `ShiftClosed`, `ShiftForceClosed`, `CashDropRecorded` |
| 3005 | Shift Close Block Policy | **Completed** | **High** | `InvariantViolationException` on close when shift not open |
| 3006 | Cash Variance Calculation | **Completed** | **High** | Expected cash derivation and variance recorded in `ShiftClosed` event |
| 3007 | Shift Repository and Projection | **Completed** | **High** | `ShiftRepositoryInterface` + `InMemoryShiftRepository` + `ShiftReadModelInterface` |

### 4000 Series - PosSession Aggregate

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 4001 | PosSession Domain Model | **Completed** | **Critical** | `PosSession` aggregate root with `Idle`/`Building`/`Checkout` state machine |
| 4002 | Session Value Objects | **Completed** | **Critical** | `SessionId`, `OrderId`, `SessionState`, `OfflineMode` |
| 4003 | Session Commands and Handlers | **Completed** | **Critical** | `StartSession`, `StartNewOrder`, `ParkOrder`, `ResumeOrder`, `EndSession`, `DeactivateOrder`, `ReactivateOrder` |
| 4004 | Session Events | **Completed** | **Critical** | `SessionStarted`, `NewOrderStarted`, `OrderParked`, `OrderResumed`, `SessionEnded`, `OrderDeactivated`, `OrderReactivated` |
| 4005 | Session Repository and Projection | **Completed** | **High** | `PosSessionRepositoryInterface` + `InMemoryPosSessionRepository` |

### 5000 Series - Checkout and Payment Orchestration

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 5001 | Checkout Flow | **Completed** | **Critical** | `InitiateCheckout` command; Draft → Confirmed transition via `OrderingServiceInterface` |
| 5002 | Payment Orchestration | **Completed** | **Critical** | `RequestPayment`; delegates to `PaymentServiceInterface`; throws on NOT OK |
| 5003 | Order Completion | **Completed** | **Critical** | `CompleteOrder`; triggers inventory deduction via `InventoryServiceInterface` |
| 5004 | Order Cancellation | **Completed** | **High** | `CancelOrder`; releases reservation and cancels via BC ports |
| 5005 | Checkout Event Handlers | **Completed** | **High** | Inline in handlers: checkout converts reservation, complete deducts inventory |

### 6000 Series - External BC Integration (Ports)

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 6001 | OrderingServiceInterface | **Completed** | **Critical** | `createDraftOrder`, `confirmOrder`, `cancelOrder`, `isOrderFullyPaid` |
| 6002 | InventoryServiceInterface | **Completed** | **Critical** | `confirmReservation`, `releaseReservation`, `fulfillOrderReservation`, `attemptReReservation` |
| 6003 | PaymentServiceInterface | **Completed** | **Critical** | `requestPaymentAuthorization`, `applyPayment` |
| 6004 | Stub Service Adapters | **Completed** | **High** | `StubOrderingService`, `StubInventoryService`, `StubPaymentService` in `tests/Stub/` |

### 7000 Series - Draft Lifecycle and Reservation Coordination

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 7001 | Draft Inactivity TTL | **Completed** | **High** | `DraftLifecycleService::shouldDeactivateOrder()` (15 min TTL) |
| 7002 | Inactive Order Resume | **Completed** | **High** | `ReactivateOrderHandler` with atomic re-reservation via `InventoryServiceInterface` |
| 7003 | Auto-Cancel Inactive | **Completed** | **Medium** | `DraftLifecycleService::isOrderExpired()` (60 min threshold) |
| 7004 | Soft to Hard Reservation | **Completed** | **High** | `InitiateCheckoutHandler` calls `convertSoftReservationToHard` on checkout |

### 8000 Series - Multi-Terminal and Concurrency

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 8001 | Optimistic Versioning | **Completed** | **Critical** | `InMemoryTerminalRepository::store(?int $expectedVersion)` with `ConcurrencyException` |
| 8002 | Command Idempotency | **Completed** | **High** | `IdempotencyRegistry` in `Application\Shared`; used by offline handlers |
| 8003 | Multi-Terminal Enforcement | **Completed** | **High** | `MultiTerminalEnforcementService` with terminal/cashier/order binding assertions |

### 9000 Series - Offline and Sync

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 9001 | Offline Draft Creation | **Completed** | **Medium** | `StartNewOrderOffline` command + `OrderCreatedOffline` event |
| 9002 | Cash-Only Offline Completion | **Completed** | **Low** | `OfflineMode` value object; offline flow supported via session state machine |
| 9003 | PendingSync Queue | **Completed** | **Medium** | `PendingSyncQueue` domain service; `OrderMarkedPendingSync` event |
| 9004 | Idempotent Replay | **Completed** | **Medium** | `SyncOrderOnline` command + `IdempotencyRegistry` prevents duplicate sync |

---

## Implementation Phases

### Phase 1: Foundation (Critical Priority)

**Goal:** Shared kernel, base classes, event store, CQRS bus integration.

Features: 1001, 1002, 1003, 1004, 1005

**Suggested Commit Message:**
```
feat(foundation): implement shared kernel with base classes, event store, and CQRS bus

- AggregateRoot, DomainEvent, ValueObject base classes
- EventStoreInterface + InMemoryEventStore
- CQRS bus integration (common-cqrs)
- Shared value objects (Money, BranchId, CashierId)
- Exception hierarchy
```

**Estimated Duration:** 1-2 weeks

---

### Phase 2: Terminal Aggregate

**Goal:** Terminal lifecycle management.

Features: 2001, 2002, 2003, 2004

**Suggested Commit Message:**
```
feat(terminal): implement Terminal aggregate with lifecycle management

- Terminal aggregate root with status tracking
- TerminalId, TerminalStatus value objects
- Register, Activate, Disable commands and handlers
- Terminal events (Registered, Activated, Disabled)
- Repository interface + in-memory implementation
- Terminal read model projection
- Unit tests
```

**Estimated Duration:** 1 week

---

### Phase 3: Shift Aggregate

**Goal:** Shift lifecycle with cash handling and close policies.

Features: 3001, 3002, 3003, 3004, 3005, 3006, 3007

**Suggested Commit Message:**
```
feat(shift): implement Shift aggregate with cash handling and close policies

- Shift aggregate root with full lifecycle
- ShiftId, ShiftStatus, Money, CashDrop value objects
- Open, Close, ForceClose, RecordCashDrop commands
- Shift events (Opened, Closed, ForceClosed, CashDropRecorded)
- Shift close block policy (no unresolved orders)
- Cash variance calculation (expected vs declared)
- Repository interface + in-memory implementation
- Shift read model projection
- Unit and integration tests
```

**Estimated Duration:** 2 weeks

---

### Phase 4: PosSession Aggregate

**Goal:** Session state machine with order parking/resuming.

Features: 4001, 4002, 4003, 4004, 4005

**Suggested Commit Message:**
```
feat(session): implement PosSession aggregate with state machine

- PosSession aggregate root with Idle/Building/Checkout states
- SessionId, OrderId, SessionState value objects
- StartSession, StartNewOrder, ParkOrder, ResumeOrder commands
- Session events (Started, OrderStarted, Parked, Resumed)
- Repository interface + in-memory implementation
- Session read model projection
- Unit tests
```

**Estimated Duration:** 1-2 weeks

---

### Phase 5: Checkout, Payment, and External BC Ports

**Goal:** Checkout flow, payment orchestration, and BC integration ports.

Features: 5001, 5002, 5003, 5004, 5005, 6001, 6002, 6003, 6004

**Suggested Commit Message:**
```
feat(checkout): implement checkout flow, payment orchestration, and BC integration ports

- InitiateCheckout: Draft -> Confirmed transition
- RequestPayment: delegate to Payment BC, act on OK/NOT OK
- CompleteOrder: mark fully paid orders as completed
- CancelOrder: cancel with reservation release
- Event handlers (OnCheckoutInitiated, OnOrderCompleted, OnOrderCancelled)
- OrderingServiceInterface, InventoryServiceInterface, PaymentServiceInterface
- Stub service adapters for testing
- Integration tests for full checkout flow
```

**Estimated Duration:** 2 weeks

---

### Phase 6: Draft Lifecycle and Reservation Coordination

**Goal:** TTL enforcement, inactive order handling, reservation conversion.

Features: 7001, 7002, 7003, 7004

**Suggested Commit Message:**
```
feat(draft-lifecycle): implement draft TTL, inactive resume, and reservation coordination

- Draft inactivity TTL policy
- Inactive order resume (same terminal + shift, atomic re-reservation)
- Auto-cancel for orders inactive > 1 hour
- Soft-to-hard reservation conversion on checkout
- Integration tests for draft lifecycle scenarios
```

**Estimated Duration:** 1-2 weeks

---

### Phase 7: Multi-Terminal and Concurrency

**Goal:** Optimistic versioning, idempotency, multi-terminal rules.

Features: 8001, 8002, 8003

**Suggested Commit Message:**
```
feat(concurrency): implement optimistic versioning, idempotency, and multi-terminal enforcement

- Aggregate version tracking for optimistic concurrency
- Unique commandId for idempotent command processing
- Multi-terminal enforcement (branch-scoped, terminal-bound drafts)
- Concurrency conflict tests
```

**Estimated Duration:** 1 week

---

### Phase 8: Offline and Sync (Future)

**Goal:** Offline capability for draft creation and cash-only completion.

Features: 9001, 9002, 9003, 9004

**Suggested Commit Message:**
```
feat(offline): implement offline draft creation and sync queue

- Offline draft creation and item scanning
- Cash-only offline completion (feature flag)
- PendingSync queue for offline orders
- Idempotent replay on reconnection
- Offline scenario tests
```

**Estimated Duration:** 2 weeks

---

## Development Guidelines

### For Each Feature

1. **Read the feature specification** in the corresponding markdown file
2. **Review dependencies** - Some features depend on others
3. **Write tests first** - Follow TDD approach
4. **Implement domain logic** - Focus on business rules
5. **Add integration tests** - Test with real scenarios
6. **Update documentation** - Keep API reference current
7. **Update feature status** - Mark progress in this README

### Testing Requirements

- **Unit Tests:** Minimum 80% code coverage
- **Integration Tests:** Cover main workflows
- **Domain Tests:** Verify business rules and invariants
- **Event Tests:** Ensure events are emitted correctly
- **Test via events/projections, NOT getters** (CQRS rule)

### Documentation Requirements

- PHPDoc blocks for all public methods
- Usage examples in feature files
- Update API reference when complete
- Implementation addendum for each completed feature

---

## Progress Tracking

### Overall Progress

- **Total Features:** 37
- **Completed:** 37
- **In Progress:** 0
- **Not Started:** 0
- **Completion:** 100% ✓ — v1.0.0 ready

### By Series

| Series | Name | Total | Completed | Progress |
|--------|------|-------|-----------|----------|
| 1000 | Foundation | 5 | 5 | 100% ✓ |
| 2000 | Terminal | 4 | 4 | 100% ✓ |
| 3000 | Shift | 7 | 7 | 100% ✓ |
| 4000 | PosSession | 5 | 5 | 100% ✓ |
| 5000 | Checkout/Payment | 5 | 5 | 100% ✓ |
| 6000 | BC Integration | 4 | 4 | 100% ✓ |
| 7000 | Draft Lifecycle | 4 | 4 | 100% ✓ |
| 8000 | Concurrency | 3 | 3 | 100% ✓ |
| 9000 | Offline/Sync | 4 | 4 | 100% ✓ |

---

## Contributing to v2+

All v1 features are complete. For future contributions:

1. Open a discussion or issue for the proposed feature
2. Read `docs/agent_workflow.md` for process guidelines
3. Create a feature branch: `git checkout -b feature/XXXX-description`
4. Write tests first (TDD)
5. Implement following DDD/ES/Hexagonal patterns
6. Update documentation
7. Commit with conventional commit message
8. Update this README with new feature entry

---

**Last Updated:** February 18, 2026
**Version:** v1.0.0 — All 37 features complete
