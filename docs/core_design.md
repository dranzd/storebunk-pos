# Core Design

## Architecture Overview

## @standard: hexagonal-architecture-principles
@category: architecture
@status: stable

The system must follow strict Hexagonal Architecture (Ports & Adapters) combined with DDD, Event Sourcing, and CQRS. The core is a framework-agnostic library with domain-centric design and event-driven state changes.

The system follows a strict **Hexagonal Architecture (Ports & Adapters)** combined with **Domain-Driven Design (DDD)**, **Event Sourcing (ES)**, and **CQRS**.

### Principles

1. **Library-First**: The core system is a PHP library. It has NO dependencies on web frameworks (Laravel, Symfony, etc.) or UI components.
2. **Domain-Centric**: The heart of the system is the Domain Model, free from infrastructure concerns.
3. **Event-Driven**: State changes are captured as domain events. Aggregates are reconstituted from events (Event Sourcing).
4. **Bounded Context**: POS is a first-class bounded context that orchestrates but never owns business truth (pricing, stock, payments, accounting).
5. **Operational Domain**: POS enforces operational discipline — terminal lifecycle, shift accountability, cash tracking, checkout boundaries.

## Common Libraries (Do NOT Re-implement)

Base infrastructure is provided by Composer dependencies:
- `dranzd/common-event-sourcing` — `AggregateRoot`, `AggregateRootTrait`, `AggregateEvent`, `AbstractAggregateEvent`, `EventStore`, `InMemoryEventStore`, `AggregateRootRepository`
- `dranzd/common-cqrs` — `Command`, `AbstractCommand`, `Query`, `AbstractQuery`, `Event`, `AbstractEvent`, buses, handler registry
- `dranzd/common-valueobject` — `ValueObject`, `Uuid`, `Money\Basic`, `Literal`, `Integer`, `Collection`, `DateTime`, `Actor`
- `dranzd/common-domain-assert` — `Assertion`
- `dranzd/common-utils` — `ArrayUtil`, `DateUtil`, `MoneyUtil`, `StringUtil`

POS aggregates implement `AggregateRoot` + use `AggregateRootTrait`. POS events extend `AbstractAggregateEvent`. POS value objects extend `Uuid` or other common-valueobject classes. POS commands extend `AbstractCommand`.

## Architectural Layers

### 1. Domain (The Core)

## @standard: domain-layer-isolation
@category: architecture
@status: stable

The domain layer must contain only pure business logic with aggregates, value objects, events, and interfaces. It must be free from infrastructure concerns and framework dependencies.

- **Aggregates**: Transaction boundaries — `Terminal`, `Shift`, `PosSession` (implement `AggregateRoot` from common-event-sourcing).
- **Value Objects**: Immutable data structures — `TerminalId`, `ShiftId`, `SessionId` (extend `Uuid` from common-valueobject), `CashDrop`.
- **Domain Events**: Facts that happened — `ShiftOpened`, `CheckoutInitiated`, `CashDropRecorded` (extend `AbstractAggregateEvent` from common-event-sourcing).
- **Repository Interfaces (Ports)**: Interfaces for saving/loading aggregates.
- **Read Model Interfaces**: Interfaces for CQRS query-side projections.
- **Service Interfaces (Ports)**: Interfaces for external BC integration — `OrderingServiceInterface`, `InventoryServiceInterface`, `PaymentServiceInterface`.

### 2. Application (Use Cases)
- **Commands**: DTOs representing user intents — `OpenShift`, `InitiateCheckout`, `RecordCashDrop`.
- **Command Handlers**: Orchestrate domain logic. They load aggregates, invoke methods, and save changes.
- **Query Handlers**: Handle read-side operations (projections).
- **Event Handlers**: React to domain events for cross-aggregate coordination.

### 3. Infrastructure (Adapters)
- **Persistence**: Implementations of repositories (e.g., InMemory for testing, SQL/EventStore for production).
- **Read Models**: Projection implementations that build query-optimized views from events.
- **Service Adapters**: Stub/real implementations of external BC service interfaces.
- **Framework Integration**: Adapters for Laravel, etc., to expose the core via HTTP/CLI.

## Event Sourcing

## @standard: event-sourcing-projections
@category: event-sourcing
@status: stable

Event Store is the single source of truth. Read models are built by listening to domain events, enabling CQRS. Aggregates must NOT have public getters - all reads go through projections.

- **Source of Truth**: The Event Store is the single source of truth.
- **Projections**: Read models (e.g., `ShiftCashSummary`, `ActiveOrders`) are built by listening to domain events. This enables CQRS (Command Query Responsibility Segregation).
- **No Public Getters on Aggregates**: Aggregate roots must NOT have public getters for querying state. All reads go through projections.

## Context Boundaries

## @standard: bounded-context-dependencies
@category: architecture
@status: stable

POS depends on other bounded contexts through ports (interfaces) only. Other BCs must never depend on POS. Integration must be event-driven with POS emitting events for downstream BCs to react independently.

POS depends on other bounded contexts through **ports (interfaces)**:

```
POS --> Ordering BC    (via OrderingServiceInterface)
POS --> Inventory BC   (via InventoryServiceInterface)
POS --> Payment BC     (via PaymentServiceInterface)
```

Other BCs never depend on POS. Integration is event-driven: POS emits events, downstream BCs react independently.

## Key Invariants

1. One cashier = one terminal per open shift
2. One terminal = one open shift at a time
3. Shift cannot close with unresolved Draft or Confirmed orders
4. Checkout locks order lines — no modifications after confirmation
5. Payment cannot apply without Confirmed state
6. Reservation TTL only applies in Draft phase
7. Confirmed orders cannot auto-expire
8. Cash drawer only affected by defined cash movements
9. No expense withdrawal in POS
10. POS never owns pricing, tax, stock deduction, or ledger logic
