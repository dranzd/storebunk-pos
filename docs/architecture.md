# StoreBunk POS - Architecture Documentation

## Overview

This library implements the **POS (Point of Sale) Bounded Context** for the StoreBunk Multi-Retail Platform using:

- **Domain-Driven Design (DDD)**
- **Event Sourcing (ES)**
- **Hexagonal Architecture (Ports & Adapters)**
- **Command Query Responsibility Segregation (CQRS)**

The POS library is **framework-agnostic** — it has zero dependencies on web frameworks (Laravel, Symfony, etc.) or UI components. It is designed to be consumed by any PHP application.

---

## Architecture Layers

### 1. Domain Layer (`src/Domain/`)

The core business logic layer, independent of infrastructure concerns.

#### **Model** (`src/Domain/Model/`)
- **Aggregates**: `Terminal`, `Shift`, `PosSession` (aggregate roots with lifecycle management)
- **Value Objects**: Immutable objects like `TerminalId`, `ShiftId`, `SessionId`, `CashierId`, `BranchId`, `Money`, `CashDrop`
- **Events**: Domain events like `ShiftOpened`, `ShiftClosed`, `CheckoutInitiated`, `CashDropRecorded`
- **Enums/Status**: `TerminalStatus`, `ShiftStatus`, `SessionState`, `OrderPhase`

#### **Repository Interfaces** (`src/Domain/Repository/`)
Interfaces that define contracts for external dependencies:
- `TerminalRepositoryInterface` — Terminal aggregate persistence
- `ShiftRepositoryInterface` — Shift aggregate persistence
- `PosSessionRepositoryInterface` — Session aggregate persistence

#### **Read Model Interfaces** (`src/Domain/ReadModel/`)
Interfaces for CQRS read-side queries:
- `TerminalReadModel` — Terminal status queries
- `ShiftReadModel` — Shift state and cash queries
- `SessionReadModel` — Active session queries

#### **Service Interfaces** (`src/Domain/Service/`)
Ports for external bounded context integration:
- `OrderingServiceInterface` — Create/confirm/cancel orders in Ordering BC
- `InventoryServiceInterface` — Reserve/release/convert reservations in Inventory BC
- `PaymentServiceInterface` — Request payment authorization from Payment BC

**Note:** Event publishing is NOT provided by this library. Consumers implement their own event publishing strategy (Laravel Events, Symfony EventDispatcher, RabbitMQ, etc.) in their repository implementations.

### 2. Application Layer (`src/Application/`)

Orchestrates use cases and business workflows.

#### **Commands** (`src/Application/Command/`)
Write operations that change state:

**Terminal Commands:**
- `RegisterTerminalCommand` / `RegisterTerminalHandler`
- `ActivateTerminalCommand` / `ActivateTerminalHandler`
- `DisableTerminalCommand` / `DisableTerminalHandler`

**Shift Commands:**
- `OpenShiftCommand` / `OpenShiftHandler`
- `CloseShiftCommand` / `CloseShiftHandler`
- `ForceCloseShiftCommand` / `ForceCloseShiftHandler`
- `RecordCashDropCommand` / `RecordCashDropHandler`

**Session Commands:**
- `StartSessionCommand` / `StartSessionHandler`
- `StartNewOrderCommand` / `StartNewOrderHandler`
- `ParkOrderCommand` / `ParkOrderHandler`
- `ResumeOrderCommand` / `ResumeOrderHandler`

**Checkout Commands:**
- `InitiateCheckoutCommand` / `InitiateCheckoutHandler`
- `RequestPaymentCommand` / `RequestPaymentHandler`
- `CompleteOrderCommand` / `CompleteOrderHandler`
- `CancelOrderCommand` / `CancelOrderHandler`

#### **Queries** (`src/Application/Query/`)
Read operations that retrieve data:
- `GetTerminalQuery` / `GetTerminalHandler`
- `GetShiftQuery` / `GetShiftHandler`
- `GetActiveSessionQuery` / `GetActiveSessionHandler`
- `GetShiftCashSummaryQuery` / `GetShiftCashSummaryHandler`
- `ListOpenOrdersQuery` / `ListOpenOrdersHandler`

#### **Event Handlers** (`src/Application/EventHandler/`)
React to domain events for cross-aggregate coordination:
- `OnCheckoutInitiated` — Convert soft reservation to hard
- `OnOrderCompleted` — Trigger inventory deduction
- `OnOrderCancelled` — Release reservations

### 3. Infrastructure Layer (`src/Infrastructure/`)

Concrete implementations of ports and technical concerns.

#### **Persistence** (`src/Infrastructure/Persistence/`)
- **EventStore**: `InMemoryEventStore` — In-memory implementation for testing
- **Repositories**: `InMemoryTerminalRepository`, `InMemoryShiftRepository`, `InMemorySessionRepository`
- **Read Models**: `InMemoryTerminalProjection`, `InMemoryShiftProjection`, `InMemorySessionProjection`

#### **Service Adapters** (`src/Infrastructure/Service/`)
- Example/stub adapters for Ordering, Inventory, and Payment service interfaces

**Note:** Repository and event store implementations are examples for testing. Consumers should implement their own repositories using proper event store libraries and handle event publishing according to their infrastructure choices.

### 4. Shared Kernel (`src/Shared/`)

Common utilities and exceptions used across layers:
- `DomainException`
- `AggregateNotFoundException`
- `ConcurrencyException`
- `InvariantViolationException`

---

## Key Patterns

### Event Sourcing

Instead of storing current state, we store a sequence of events:

```php
// Events are the source of truth
ShiftOpened -> CashDropRecorded -> CheckoutInitiated -> ShiftClosed
```

The aggregate state is reconstructed by replaying events.

### CQRS

Separate models for reads and writes:

- **Write Model**: Aggregates (Terminal, Shift, PosSession) + Event Store
- **Read Model**: Projections for fast queries (terminal status, shift cash summary, active orders)

#### Read Model Interface Pattern

All projections follow the **Interface Segregation Principle** with separate read model interfaces and concrete implementations:

**Interface Layer** (`src/Domain/ReadModel/*ReadModel.php`):
- `TerminalReadModel` — Query methods for terminal state
- `ShiftReadModel` — Query methods for shift state and cash
- `SessionReadModel` — Query methods for active sessions

**Implementation Layer** (`src/Infrastructure/Persistence/ReadModel/InMemory*.php`):
- `InMemoryTerminalProjection` — In-memory implementation
- `InMemoryShiftProjection` — In-memory implementation
- `InMemorySessionProjection` — In-memory implementation

**Benefits:**
- **Flexibility**: Easy to swap implementations (MySQL, Redis, Elasticsearch, etc.)
- **Testability**: Mock interfaces in tests without concrete dependencies
- **Separation of Concerns**: Query-only consumers use interfaces; event handlers use concrete implementations
- **Future-Proof**: Enables persistent storage implementations without changing consumers

### Hexagonal Architecture

```
┌─────────────────────────────────────────────────┐
│          Application Layer (Use Cases)           │
│   Commands, Queries, Handlers, Event Handlers    │
└──────────────────┬──────────────────────────────┘
                   │
       ┌───────────┴───────────┐
       │                       │
  ┌────▼─────┐          ┌─────▼─────┐
  │  Domain   │          │   Ports   │ (Interfaces)
  │  Model    │          │           │
  └──────────┘          └─────┬─────┘
                              │
                  ┌───────────┴───────────┐
                  │                       │
           ┌──────▼──────┐         ┌─────▼──────┐
           │Infrastructure│        │  Adapters   │
           │ (Event Store)│        │ (Services)  │
           └──────────────┘        └─────────────┘
```

### Context Map — Dependency Direction

```
POS BC ──→ Ordering BC    (via OrderingServiceInterface)
POS BC ──→ Inventory BC   (via InventoryServiceInterface)
POS BC ──→ Payment BC     (via PaymentServiceInterface)

Ordering BC ──✗──→ POS BC   (never)
Inventory BC ──✗──→ POS BC  (never)
Payment BC ──✗──→ POS BC    (never)
```

POS depends on other BCs through **ports (interfaces)**. Other BCs never depend on POS. Integration is event-driven: POS emits events, downstream BCs react independently.

---

## CQRS Bus Integration

The library integrates with `dranzd/common-cqrs` for full CQRS bus support:

```
Command/Query → Bus → InMemoryHandlerRegistry → Handler → Repository
```

- **Commands** extend `AbstractCommand`, handlers implement `Command\Handler`
- **Queries** extend `AbstractQuery`, handlers implement `Query\Handler`
- **`InMemoryHandlerRegistry`** maps message classes to handlers
- **`SimpleCommandBus`** / **`SimpleQueryBus`** dispatch through the registry

---

## Business Rules Summary

1. **One cashier = one terminal per open shift**
2. **One terminal = one open shift at a time**
3. **Shift cannot close with unresolved Draft or Confirmed orders**
4. **Checkout locks order lines** — no modifications after confirmation
5. **Payment cannot apply without Confirmed state**
6. **Reservation TTL only applies in Draft phase**
7. **Confirmed orders cannot auto-expire**
8. **Cash drawer only affected by defined cash movements**
9. **No expense withdrawal in POS**
10. **POS never owns pricing, tax, stock deduction, or ledger logic**

---

## Events

### Published Events

**Terminal Aggregate:**
- `dranzd.storebunk.pos.terminal.registered`
- `dranzd.storebunk.pos.terminal.activated`
- `dranzd.storebunk.pos.terminal.disabled`

**Shift Aggregate:**
- `dranzd.storebunk.pos.shift.opened`
- `dranzd.storebunk.pos.shift.closed`
- `dranzd.storebunk.pos.shift.force_closed`
- `dranzd.storebunk.pos.shift.cash_drop_recorded`

**Session Aggregate:**
- `dranzd.storebunk.pos.session.started`
- `dranzd.storebunk.pos.session.order_started`
- `dranzd.storebunk.pos.session.order_parked`
- `dranzd.storebunk.pos.session.order_resumed`
- `dranzd.storebunk.pos.session.checkout_initiated`
- `dranzd.storebunk.pos.session.payment_requested`
- `dranzd.storebunk.pos.session.order_completed`
- `dranzd.storebunk.pos.session.order_cancelled`

### Consumed Events (from other domains)
- `dranzd.storebunk.ordering.order.confirmed` — Order state sync
- `dranzd.storebunk.inventory.reservation.expired` — Draft order expiration
- `dranzd.storebunk.payment.authorization.completed` — Payment result

---

## Dependencies

- `php: ^8.3`
- `ramsey/uuid: ^4.7` — UUID generation
- `dranzd/common-event-sourcing: dev-master` — Event sourcing infrastructure
- `dranzd/common-cqrs: dev-master` — CQRS bus infrastructure (commands, queries, handler registry)

---

## Testing

Run tests with PHPUnit:

```bash
composer test
```

---

## Next Steps

1. Implement core aggregates (Terminal, Shift, PosSession)
2. Implement value objects and domain events
3. Implement command/query handlers
4. Implement in-memory repositories and projections
5. Add domain service interfaces for external BCs
6. Add integration tests
7. Add demo CLI tool
8. Configure production event bus adapters

---

## See Also

- [Domain Vision](domain-vision.md) — Business context and domain boundaries
- [Domain Model](domain-model.md) — Aggregates, Commands, Events, Policies
- [Folder Structure](folder-structure.md) — Complete directory reference
- [Features](features/README.md) — Phased implementation checklist
