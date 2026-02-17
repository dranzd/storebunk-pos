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
| 1001 | Base Classes | Not Started | **Critical** | AggregateRoot, DomainEvent, ValueObject base classes |
| 1002 | Event Store Interface | Not Started | **Critical** | Event store interface + in-memory implementation |
| 1003 | CQRS Bus Integration | Not Started | **Critical** | Command bus, query bus, handler registry |
| 1004 | Shared Value Objects | Not Started | **Critical** | Money, BranchId, CashierId and other cross-aggregate VOs |
| 1005 | Exception Hierarchy | Not Started | **High** | DomainException, AggregateNotFoundException, InvariantViolationException |

### 2000 Series - Terminal Aggregate

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 2001 | Terminal Domain Model | Not Started | **Critical** | Terminal aggregate, TerminalId, TerminalStatus |
| 2002 | Terminal Commands and Handlers | Not Started | **Critical** | Register, Activate, Disable terminal |
| 2003 | Terminal Events | Not Started | **Critical** | TerminalRegistered, TerminalActivated, TerminalDisabled |
| 2004 | Terminal Repository and Projection | Not Started | **High** | Repository interface + in-memory impl + read model |

### 3000 Series - Shift Aggregate

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 3001 | Shift Domain Model | Not Started | **Critical** | Shift aggregate root with full lifecycle |
| 3002 | Shift Value Objects | Not Started | **Critical** | ShiftId, ShiftStatus, Money, CashDrop |
| 3003 | Shift Commands and Handlers | Not Started | **Critical** | OpenShift, CloseShift, ForceCloseShift, RecordCashDrop |
| 3004 | Shift Events | Not Started | **Critical** | ShiftOpened, ShiftClosed, ShiftForceClosed, CashDropRecorded |
| 3005 | Shift Close Block Policy | Not Started | **High** | Enforce no unresolved orders on shift close |
| 3006 | Cash Variance Calculation | Not Started | **High** | Expected cash derivation and variance recording |
| 3007 | Shift Repository and Projection | Not Started | **High** | Repository interface + in-memory impl + read model |

### 4000 Series - PosSession Aggregate

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 4001 | PosSession Domain Model | Not Started | **Critical** | PosSession aggregate root with state machine |
| 4002 | Session Value Objects | Not Started | **Critical** | SessionId, OrderId, SessionState |
| 4003 | Session Commands and Handlers | Not Started | **Critical** | StartSession, StartNewOrder, ParkOrder, ResumeOrder |
| 4004 | Session Events | Not Started | **Critical** | SessionStarted, NewOrderStarted, OrderParked, OrderResumed |
| 4005 | Session Repository and Projection | Not Started | **High** | Repository interface + in-memory impl + read model |

### 5000 Series - Checkout and Payment Orchestration

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 5001 | Checkout Flow | Not Started | **Critical** | InitiateCheckout command, Draft to Confirmed transition |
| 5002 | Payment Orchestration | Not Started | **Critical** | RequestPayment, act on OK/NOT OK from Payment BC |
| 5003 | Order Completion | Not Started | **Critical** | CompleteOrder when fully paid |
| 5004 | Order Cancellation | Not Started | **High** | CancelOrder with reservation release |
| 5005 | Checkout Event Handlers | Not Started | **High** | OnCheckoutInitiated, OnOrderCompleted, OnOrderCancelled |

### 6000 Series - External BC Integration (Ports)

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 6001 | OrderingServiceInterface | Not Started | **Critical** | Port for Ordering BC integration |
| 6002 | InventoryServiceInterface | Not Started | **Critical** | Port for Inventory BC reservation handling |
| 6003 | PaymentServiceInterface | Not Started | **Critical** | Port for Payment BC authorization |
| 6004 | Stub Service Adapters | Not Started | **High** | In-memory stub implementations for testing |

### 7000 Series - Draft Lifecycle and Reservation Coordination

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 7001 | Draft Inactivity TTL | Not Started | **High** | Draft order expiration policy |
| 7002 | Inactive Order Resume | Not Started | **High** | Resume with atomic re-reservation (same terminal + shift) |
| 7003 | Auto-Cancel Inactive | Not Started | **Medium** | Cancel orders inactive longer than 1 hour |
| 7004 | Soft to Hard Reservation | Not Started | **High** | Convert reservation on checkout |

### 8000 Series - Multi-Terminal and Concurrency

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 8001 | Optimistic Versioning | Not Started | **Critical** | Aggregate version tracking for concurrency |
| 8002 | Command Idempotency | Not Started | **High** | Unique commandId for idempotent operations |
| 8003 | Multi-Terminal Enforcement | Not Started | **High** | Branch-scoped inventory, terminal-bound drafts |

### 9000 Series - Offline and Sync (Future)

| ID | Feature | Status | Priority | Description |
|----|---------|--------|----------|-------------|
| 9001 | Offline Draft Creation | Not Started | **Medium** | Create drafts and scan items offline |
| 9002 | Cash-Only Offline Completion | Not Started | **Low** | Complete cash-only orders offline (feature flag) |
| 9003 | PendingSync Queue | Not Started | **Medium** | Mark offline orders for sync on reconnection |
| 9004 | Idempotent Replay | Not Started | **Medium** | Replay commands with idempotency keys on reconnect |

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
- **Completed:** 0
- **In Progress:** 0
- **Not Started:** 37
- **Completion:** 0%

### By Series

| Series | Name | Total | Completed | Progress |
|--------|------|-------|-----------|----------|
| 1000 | Foundation | 5 | 0 | 0% |
| 2000 | Terminal | 4 | 0 | 0% |
| 3000 | Shift | 7 | 0 | 0% |
| 4000 | PosSession | 5 | 0 | 0% |
| 5000 | Checkout/Payment | 5 | 0 | 0% |
| 6000 | BC Integration | 4 | 0 | 0% |
| 7000 | Draft Lifecycle | 4 | 0 | 0% |
| 8000 | Concurrency | 3 | 0 | 0% |
| 9000 | Offline/Sync | 4 | 0 | 0% |

---

## Quick Start for Contributors

1. Choose a feature from Phase 1 (Foundation)
2. Read the feature specification file
3. Check dependencies in the feature file
4. Create a feature branch: `git checkout -b feature/1001-base-classes`
5. Write tests first
6. Implement the feature
7. Update documentation
8. Commit with suggested commit message
9. Update this README with progress

---

**Last Updated:** February 18, 2026
**Next Review:** Start of Phase 1 implementation
