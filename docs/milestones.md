# Milestones

## Phase 1: Foundation (Features 1001-1005)

**Goal:** Shared kernel, base classes, event store, CQRS bus integration.

- [ ] AggregateRoot, DomainEvent, ValueObject base classes
- [ ] EventStoreInterface + InMemoryEventStore
- [ ] CQRS bus integration (dranzd/common-cqrs, dranzd/common-event-sourcing)
- [ ] Shared value objects (Money, BranchId, CashierId)
- [ ] Exception hierarchy (DomainException, AggregateNotFoundException, InvariantViolationException, ConcurrencyException)
- [ ] Fix composer.json (autoload, dependencies, PHP 8.3)

**Commit:**
```
feat(foundation): implement shared kernel with base classes, event store, and CQRS bus
```

---

## Phase 2: Terminal Aggregate (Features 2001-2004)

**Goal:** Terminal lifecycle management.

- [ ] Terminal aggregate root with TerminalId, TerminalStatus
- [ ] Register, Activate, Disable commands and handlers
- [ ] Terminal events (Registered, Activated, Disabled, MaintenanceSet)
- [ ] Repository interface + in-memory implementation
- [ ] Terminal read model projection
- [ ] Unit tests

**Commit:**
```
feat(terminal): implement Terminal aggregate with lifecycle management
```

---

## Phase 3: Shift Aggregate (Features 3001-3007)

**Goal:** Shift lifecycle with cash handling and close policies.

- [ ] Shift aggregate root with full lifecycle
- [ ] ShiftId, ShiftStatus, Money, CashDrop value objects
- [ ] Open, Close, ForceClose, RecordCashDrop commands and handlers
- [ ] Shift events (Opened, Closed, ForceClosed, CashDropRecorded)
- [ ] Shift close block policy (no unresolved orders)
- [ ] Cash variance calculation (expected vs declared)
- [ ] Repository interface + in-memory implementation + read model
- [ ] Unit and integration tests

**Commit:**
```
feat(shift): implement Shift aggregate with cash handling and close policies
```

---

## Phase 4: PosSession Aggregate (Features 4001-4005)

**Goal:** Session state machine with order parking/resuming.

- [ ] PosSession aggregate root with Idle/Building/Checkout states
- [ ] SessionId, OrderId, SessionState value objects
- [ ] StartSession, StartNewOrder, ParkOrder, ResumeOrder commands and handlers
- [ ] Session events (Started, OrderStarted, Parked, Resumed, Ended)
- [ ] Repository interface + in-memory implementation + read model
- [ ] Unit tests

**Commit:**
```
feat(session): implement PosSession aggregate with state machine
```

---

## Phase 5: Checkout, Payment, and External BC Ports (Features 5001-5005, 6001-6004)

**Goal:** Checkout flow, payment orchestration, and BC integration ports.

- [ ] InitiateCheckout: Draft to Confirmed transition
- [ ] RequestPayment: delegate to Payment BC, act on OK/NOT OK
- [ ] CompleteOrder: mark fully paid orders as completed
- [ ] CancelOrder: cancel with reservation release
- [ ] Event handlers (OnCheckoutInitiated, OnOrderCompleted, OnOrderCancelled)
- [ ] OrderingServiceInterface, InventoryServiceInterface, PaymentServiceInterface
- [ ] Stub service adapters for testing
- [ ] Integration tests for full checkout flow

**Commit:**
```
feat(checkout): implement checkout flow, payment orchestration, and BC integration ports
```

---

## Phase 6: Draft Lifecycle and Reservation Coordination (Features 7001-7004)

**Goal:** TTL enforcement, inactive order handling, reservation conversion.

- [ ] Draft inactivity TTL policy
- [ ] Inactive order resume (same terminal + shift, atomic re-reservation)
- [ ] Auto-cancel for orders inactive > 1 hour
- [ ] Soft-to-hard reservation conversion on checkout
- [ ] Integration tests for draft lifecycle scenarios

**Commit:**
```
feat(draft-lifecycle): implement draft TTL, inactive resume, and reservation coordination
```

---

## Phase 7: Multi-Terminal and Concurrency (Features 8001-8003)

**Goal:** Optimistic versioning, idempotency, multi-terminal rules.

- [ ] Aggregate version tracking for optimistic concurrency
- [ ] Unique commandId for idempotent command processing
- [ ] Multi-terminal enforcement (branch-scoped, terminal-bound drafts)
- [ ] Concurrency conflict tests

**Commit:**
```
feat(concurrency): implement optimistic versioning, idempotency, and multi-terminal enforcement
```

---

## Phase 8: Offline and Sync - Future (Features 9001-9004)

**Goal:** Offline capability for draft creation and cash-only completion.

- [ ] Offline draft creation and item scanning
- [ ] Cash-only offline completion (feature flag)
- [ ] PendingSync queue for offline orders
- [ ] Idempotent replay on reconnection
- [ ] Offline scenario tests

**Commit:**
```
feat(offline): implement offline draft creation and sync queue
```
