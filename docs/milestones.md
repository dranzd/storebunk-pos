# Milestones

## Phase 1: Foundation (Features 1001-1005) ✓ COMPLETE

**Goal:** Project setup, verify common library integration, POS-specific exceptions, Docker environment, quality tooling.

**Note:** Base classes (`AggregateRoot`, `AggregateRootTrait`, `AggregateEvent`, `AbstractAggregateEvent`, `EventStore`, `InMemoryEventStore`, `AggregateRootRepository`), CQRS infrastructure (`Command`, `Query`, `Event`, buses, handler registry), and value object primitives (`ValueObject`, `Uuid`, `Money\Basic`) are all provided by the common libraries (`dranzd/common-event-sourcing`, `dranzd/common-cqrs`, `dranzd/common-valueobject`). **Do NOT re-implement them.**

- [x] Fix composer.json (autoload, dependencies, PHP 8.3)
- [x] Verify common library integration (common-event-sourcing, common-cqrs, common-valueobject)
- [x] Exception hierarchy (DomainException, AggregateNotFoundException, InvariantViolationException, ConcurrencyException)
- [x] DomainEventInterface marker for POS-specific events
- [x] Docker environment setup (Dockerfile, docker-compose.yml, utils script)
- [x] Quality tooling (PHPUnit, PHPStan, PHPCS configurations)
- [x] Write tests for exception hierarchy and library integration verification

**Commit:**
```
feat(foundation): project setup with common library integration, exceptions, and quality tooling
```

---

## Phase 2: Terminal Aggregate (Features 2001-2004) ✓ COMPLETE

**Goal:** Terminal lifecycle management.

- [x] Terminal aggregate root with TerminalId, TerminalStatus
- [x] Register, Activate, Disable commands and handlers
- [x] Terminal events (Registered, Activated, Disabled, MaintenanceSet)
- [x] Repository interface + in-memory implementation
- [x] Terminal read model projection
- [x] Unit tests

**Commit:**
```
feat(terminal): implement Terminal aggregate with lifecycle management
```

---

## Phase 3: Shift Aggregate (Features 3001-3007) ✓ COMPLETE

**Goal:** Shift lifecycle with cash handling and close policies.

- [x] Shift aggregate root with full lifecycle
- [x] ShiftId, ShiftStatus, Money, CashDrop value objects
- [x] Open, Close, ForceClose, RecordCashDrop commands and handlers
- [x] Shift events (Opened, Closed, ForceClosed, CashDropRecorded)
- [x] Shift close block policy (no unresolved orders)
- [x] Cash variance calculation (expected vs declared)
- [x] Repository interface + in-memory implementation + read model
- [x] Unit and integration tests

**Commit:**
```
feat(shift): implement Shift aggregate with cash handling and close policies
```

---

## Phase 4: PosSession Aggregate (Features 4001-4005) ✓ COMPLETE

**Goal:** Session state machine with order parking/resuming.

- [x] PosSession aggregate root with Idle/Building/Checkout states
- [x] SessionId, OrderId, SessionState value objects
- [x] StartSession, StartNewOrder, ParkOrder, ResumeOrder commands and handlers
- [x] Session events (Started, OrderStarted, Parked, Resumed, Ended)
- [x] Repository interface + in-memory implementation + read model
- [x] Unit tests

**Commit:**
```
feat(session): implement PosSession aggregate with state machine
```

---

## Phase 5: Checkout, Payment, and External BC Ports (Features 5001-5005, 6001-6004) ✓ COMPLETE

**Goal:** Checkout flow, payment orchestration, and BC integration ports.

- [x] InitiateCheckout: Draft to Confirmed transition
- [x] RequestPayment: delegate to Payment BC, act on OK/NOT OK
- [x] CompleteOrder: mark fully paid orders as completed
- [x] CancelOrder: cancel with reservation release
- [x] Event handlers (OnCheckoutInitiated, OnOrderCompleted, OnOrderCancelled)
- [x] OrderingServiceInterface, InventoryServiceInterface, PaymentServiceInterface
- [x] Stub service adapters for testing
- [x] Integration tests for full checkout flow

**Commit:**
```
feat(checkout): implement checkout flow, payment orchestration, and BC integration ports
```

---

## Phase 6: Draft Lifecycle and Reservation Coordination (Features 7001-7004) ✓ COMPLETE

**Goal:** TTL enforcement, inactive order handling, reservation conversion.

- [x] Draft inactivity TTL policy
- [x] Inactive order resume (same terminal + shift, atomic re-reservation)
- [x] Auto-cancel for orders inactive > 1 hour
- [x] Soft-to-hard reservation conversion on checkout
- [x] Integration tests for draft lifecycle scenarios

**Commit:**
```
feat(draft-lifecycle): implement draft TTL, inactive resume, and reservation coordination
```

---

## Phase 7: Multi-Terminal and Concurrency (Features 8001-8003) ✓ COMPLETE

**Goal:** Optimistic versioning, idempotency, multi-terminal rules.

- [x] Aggregate version tracking for optimistic concurrency
- [x] Unique commandId for idempotent command processing
- [x] Multi-terminal enforcement (branch-scoped, terminal-bound drafts)
- [x] Concurrency conflict tests

**Commit:**
```
feat(concurrency): implement optimistic versioning, idempotency, and multi-terminal enforcement
```

---

## Phase 8: Offline and Sync (Features 9001-9004) ✓ COMPLETE

**Goal:** Offline capability for draft creation and cash-only completion.

- [x] Offline draft creation and item scanning
- [x] Cash-only offline completion (feature flag)
- [x] PendingSync queue for offline orders
- [x] Idempotent replay on reconnection
- [x] Offline scenario tests

**Commit:**
```
feat(offline): implement offline draft creation and sync queue
```
