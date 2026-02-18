# Folder Structure Reference

## Complete Directory Tree

```
storebunk-pos/
├── src/
│   ├── Domain/                              # Core business logic
│   │   ├── Model/                           # Domain models
│   │   │   ├── Terminal/
│   │   │   │   ├── Terminal.php             # Aggregate Root
│   │   │   │   ├── ValueObject/
│   │   │   │   │   ├── TerminalId.php
│   │   │   │   │   └── TerminalStatus.php   # Enum: Active, Disabled, Maintenance
│   │   │   │   └── Event/
│   │   │   │       ├── TerminalRegistered.php
│   │   │   │       ├── TerminalActivated.php
│   │   │   │       ├── TerminalDisabled.php
│   │   │   │       └── TerminalMaintenanceSet.php
│   │   │   │
│   │   │   ├── Shift/
│   │   │   │   ├── Shift.php                # Aggregate Root
│   │   │   │   ├── ValueObject/
│   │   │   │   │   ├── ShiftId.php
│   │   │   │   │   ├── CashierId.php
│   │   │   │   │   ├── BranchId.php
│   │   │   │   │   ├── Money.php
│   │   │   │   │   ├── CashDrop.php
│   │   │   │   │   └── ShiftStatus.php      # Enum: Open, Closed, ForcedClosed
│   │   │   │   └── Event/
│   │   │   │       ├── ShiftOpened.php
│   │   │   │       ├── ShiftClosed.php
│   │   │   │       ├── ShiftForceClosed.php
│   │   │   │       └── CashDropRecorded.php
│   │   │   │
│   │   │   └── PosSession/
│   │   │       ├── PosSession.php           # Aggregate Root
│   │   │       ├── ValueObject/
│   │   │       │   ├── SessionId.php
│   │   │       │   ├── OrderId.php
│   │   │       │   └── SessionState.php     # Enum: Idle, Building, Checkout
│   │   │       └── Event/
│   │   │           ├── SessionStarted.php
│   │   │           ├── NewOrderStarted.php
│   │   │           ├── OrderParked.php
│   │   │           ├── OrderResumed.php
│   │   │           ├── CheckoutInitiated.php
│   │   │           ├── PaymentRequested.php
│   │   │           ├── OrderCompleted.php
│   │   │           ├── OrderCancelledViaPOS.php
│   │   │           └── SessionEnded.php
│   │   │
│   │   ├── Repository/                      # Repository interfaces (Ports)
│   │   │   ├── TerminalRepositoryInterface.php
│   │   │   ├── ShiftRepositoryInterface.php
│   │   │   └── PosSessionRepositoryInterface.php
│   │   │
│   │   ├── ReadModel/                       # CQRS read model interfaces
│   │   │   ├── TerminalReadModel.php
│   │   │   ├── ShiftReadModel.php
│   │   │   └── SessionReadModel.php
│   │   │
│   │   └── Service/                         # Domain service interfaces (Ports to other BCs)
│   │       ├── OrderingServiceInterface.php
│   │       ├── InventoryServiceInterface.php
│   │       └── PaymentServiceInterface.php
│   │
│   ├── Application/                         # Use cases and orchestration
│   │   ├── Command/                         # Write operations (CQRS)
│   │   │   ├── Terminal/
│   │   │   │   ├── RegisterTerminalCommand.php
│   │   │   │   ├── RegisterTerminalHandler.php
│   │   │   │   ├── ActivateTerminalCommand.php
│   │   │   │   ├── ActivateTerminalHandler.php
│   │   │   │   ├── DisableTerminalCommand.php
│   │   │   │   └── DisableTerminalHandler.php
│   │   │   ├── Shift/
│   │   │   │   ├── OpenShiftCommand.php
│   │   │   │   ├── OpenShiftHandler.php
│   │   │   │   ├── CloseShiftCommand.php
│   │   │   │   ├── CloseShiftHandler.php
│   │   │   │   ├── ForceCloseShiftCommand.php
│   │   │   │   ├── ForceCloseShiftHandler.php
│   │   │   │   ├── RecordCashDropCommand.php
│   │   │   │   └── RecordCashDropHandler.php
│   │   │   └── Session/
│   │   │       ├── StartSessionCommand.php
│   │   │       ├── StartSessionHandler.php
│   │   │       ├── StartNewOrderCommand.php
│   │   │       ├── StartNewOrderHandler.php
│   │   │       ├── ParkOrderCommand.php
│   │   │       ├── ParkOrderHandler.php
│   │   │       ├── ResumeOrderCommand.php
│   │   │       ├── ResumeOrderHandler.php
│   │   │       ├── InitiateCheckoutCommand.php
│   │   │       ├── InitiateCheckoutHandler.php
│   │   │       ├── RequestPaymentCommand.php
│   │   │       ├── RequestPaymentHandler.php
│   │   │       ├── CompleteOrderCommand.php
│   │   │       ├── CompleteOrderHandler.php
│   │   │       ├── CancelOrderCommand.php
│   │   │       └── CancelOrderHandler.php
│   │   │
│   │   ├── Query/                           # Read operations (CQRS)
│   │   │   ├── Terminal/
│   │   │   │   ├── GetTerminalQuery.php
│   │   │   │   └── GetTerminalHandler.php
│   │   │   ├── Shift/
│   │   │   │   ├── GetShiftQuery.php
│   │   │   │   ├── GetShiftHandler.php
│   │   │   │   ├── GetShiftCashSummaryQuery.php
│   │   │   │   └── GetShiftCashSummaryHandler.php
│   │   │   └── Session/
│   │   │       ├── GetActiveSessionQuery.php
│   │   │       ├── GetActiveSessionHandler.php
│   │   │       ├── ListOpenOrdersQuery.php
│   │   │       └── ListOpenOrdersHandler.php
│   │   │
│   │   └── EventHandler/                    # Cross-aggregate event reactions
│   │       ├── OnCheckoutInitiated.php
│   │       ├── OnOrderCompleted.php
│   │       └── OnOrderCancelled.php
│   │
│   ├── Infrastructure/                      # Technical implementations
│   │   └── Persistence/
│   │       ├── EventStore/
│   │       │   ├── EventStoreInterface.php
│   │       │   └── InMemoryEventStore.php
│   │       ├── Repository/
│   │       │   ├── InMemoryTerminalRepository.php
│   │       │   ├── InMemoryShiftRepository.php
│   │       │   └── InMemorySessionRepository.php
│   │       └── ReadModel/
│   │           ├── InMemoryTerminalProjection.php
│   │           ├── InMemoryShiftProjection.php
│   │           └── InMemorySessionProjection.php
│   │
│   └── Shared/                              # POS-specific shared utilities
│       └── Exception/
│           ├── DomainException.php
│           ├── AggregateNotFoundException.php
│           ├── ConcurrencyException.php
│           └── InvariantViolationException.php
│
├── tests/
│   ├── Helpers/
│   │   ├── EventAssertions.php              # Trait for asserting events
│   │   └── SimpleContainer.php              # Minimal PSR-11 container for testing
│   ├── Unit/
│   │   └── Domain/
│   │       └── Model/
│   │           ├── Terminal/
│   │           │   └── TerminalTest.php
│   │           ├── Shift/
│   │           │   └── ShiftTest.php
│   │           └── PosSession/
│   │               └── PosSessionTest.php
│   └── Integration/
│       ├── ShiftLifecycleTest.php
│       ├── CheckoutFlowTest.php
│       └── CashHandlingTest.php
│
├── docs/
│   ├── README.md                            # Documentation index
│   ├── domain-vision.md                     # Business context and philosophy
│   ├── architecture.md                      # DDD, ES, Hexagonal, CQRS patterns
│   ├── domain-model.md                      # Aggregates, Commands, Events, Policies
│   ├── folder-structure.md                  # This file
│   ├── core_design.md                       # Architectural principles
│   ├── technical_design.md                  # Implementation details
│   ├── milestones.md                        # Phased roadmap with commit messages
│   ├── tasks.md                             # Active task tracking
│   ├── agent_workflow.md                    # AI agent guidelines
│   ├── features/                            # Feature specifications
│   │   └── README.md                        # Feature index with status tracking
│   └── raw-discussions/                     # Raw design discussions
│       └── 20260218-0324.md                 # Initial POS concept discussion
│
├── .windsurf/
│   └── workflows/
│       └── branch-protection.md             # Branch protection workflow
│
├── composer.json
├── phpunit.xml
├── Dockerfile
├── docker-compose.yml
├── utils                                    # Docker management script
└── .gitignore
```

## Layer Responsibilities

### Domain Layer
- **Pure business logic** — No framework dependencies
- **Aggregates** enforce business rules and invariants
- **Value Objects** are immutable and self-validating
- **Events** represent facts that happened
- **Repository Interfaces** define contracts without implementation
- **Service Interfaces** define ports to external bounded contexts
- **Read Model Interfaces** define query contracts

### Application Layer
- **Orchestrates** domain objects to fulfill use cases
- **Commands** represent intentions to change state
- **Queries** represent requests for data
- **Handlers** execute commands and queries
- **Event Handlers** react to domain events for cross-aggregate coordination

### Infrastructure Layer
- **Implements** ports with concrete technology
- **Event Store** persists event streams
- **Repositories** implement domain repository interfaces
- **Projections** build read models from events

### Shared Kernel
- **Base Classes** provided by common libraries (see Dependencies below):
  - `dranzd/common-event-sourcing` — `AggregateRoot`, `AggregateRootTrait`, `AggregateEvent`, `AbstractAggregateEvent`, `EventStore`, `InMemoryEventStore`, `AggregateRootRepository`
  - `dranzd/common-cqrs` — `Command`, `AbstractCommand`, `Query`, `AbstractQuery`, `Event`, `AbstractEvent`, `SimpleCommandBus`, `SimpleQueryBus`, `SimpleEventBus`, `InMemoryHandlerRegistry`
  - `dranzd/common-valueobject` — `ValueObject`, `Uuid`, `Money\Basic`, `Literal`, `Integer`, `Collection`, `DateTime`, `Actor`
  - `dranzd/common-domain-assert` — `Assertion`
  - `dranzd/common-utils` — `ArrayUtil`, `DateUtil`, `MoneyUtil`, `StringUtil`
- **POS-specific Exceptions** for domain errors (`DomainException`, `AggregateNotFoundException`, `ConcurrencyException`, `InvariantViolationException`)

---

## Naming Conventions

### Files
- **Aggregates**: `{Name}.php` (e.g., `Shift.php`)
- **Value Objects**: `{Name}.php` (e.g., `ShiftId.php`, `Money.php`)
- **Enums**: `{Name}.php` (e.g., `ShiftStatus.php`)
- **Events**: `{ActionPastTense}.php` (e.g., `ShiftOpened.php`, `CashDropRecorded.php`)
- **Commands**: `{Action}{Entity}Command.php` (e.g., `OpenShiftCommand.php`)
- **Handlers**: `{Action}{Entity}Handler.php` (e.g., `OpenShiftHandler.php`)
- **Queries**: `{Action}{Entity}Query.php` (e.g., `GetShiftQuery.php`)
- **Interfaces**: `{Name}Interface.php` (e.g., `ShiftRepositoryInterface.php`)
- **Read Models**: `{Name}ReadModel.php` (e.g., `ShiftReadModel.php`)
- **Projections**: `InMemory{Name}Projection.php` (e.g., `InMemoryShiftProjection.php`)

### Namespaces
- Domain: `Dranzd\StorebunkPos\Domain\{Layer}\{Context}`
- Application: `Dranzd\StorebunkPos\Application\{Type}\{UseCase}`
- Infrastructure: `Dranzd\StorebunkPos\Infrastructure\{Technology}`
- Shared: `Dranzd\StorebunkPos\Shared\{Layer}`

---

## Adding New Features

### New Aggregate
1. Create aggregate in `src/Domain/Model/{Context}/{Aggregate}.php`
2. Add value objects in `src/Domain/Model/{Context}/ValueObject/`
3. Add events in `src/Domain/Model/{Context}/Event/`
4. Create repository interface in `src/Domain/Repository/`
5. Create read model interface in `src/Domain/ReadModel/`
6. Implement in-memory repository in `src/Infrastructure/Persistence/Repository/`
7. Implement in-memory projection in `src/Infrastructure/Persistence/ReadModel/`

### New Use Case
1. Create command/query in `src/Application/{Type}/{Context}/`
2. Create handler in same directory
3. Wire up in dependency injection container

### New External BC Integration
1. Create service interface in `src/Domain/Service/`
2. Implement stub adapter in `src/Infrastructure/Service/`
3. Consumer provides real adapter
