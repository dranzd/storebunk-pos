# Folder Structure Reference

## Complete Directory Tree

```
storebunk-pos/
├── src/
│   ├── Domain/                              # Core business logic
│   │   ├── Event/
│   │   │   └── DomainEventInterface.php     # POS marker interface for domain events
│   │   │
│   │   ├── Model/                           # Domain models (per-context)
│   │   │   ├── Terminal/
│   │   │   │   ├── Terminal.php             # Aggregate Root
│   │   │   │   ├── ValueObject/
│   │   │   │   │   ├── TerminalId.php
│   │   │   │   │   ├── BranchId.php
│   │   │   │   │   └── TerminalStatus.php   # Enum: Active, Disabled, Maintenance
│   │   │   │   ├── Event/
│   │   │   │   │   ├── TerminalRegistered.php
│   │   │   │   │   ├── TerminalActivated.php
│   │   │   │   │   ├── TerminalDisabled.php
│   │   │   │   │   └── TerminalMaintenanceSet.php
│   │   │   │   └── Repository/
│   │   │   │       └── TerminalRepositoryInterface.php
│   │   │   │
│   │   │   ├── Shift/
│   │   │   │   ├── Shift.php                # Aggregate Root
│   │   │   │   ├── ValueObject/
│   │   │   │   │   ├── ShiftId.php
│   │   │   │   │   ├── CashierId.php
│   │   │   │   │   ├── CashDrop.php
│   │   │   │   │   └── ShiftStatus.php      # Enum: Open, Closed, ForceClosed
│   │   │   │   ├── Event/
│   │   │   │   │   ├── ShiftOpened.php
│   │   │   │   │   ├── ShiftClosed.php
│   │   │   │   │   ├── ShiftForceClosed.php
│   │   │   │   │   └── CashDropRecorded.php
│   │   │   │   └── Repository/
│   │   │   │       └── ShiftRepositoryInterface.php
│   │   │   │
│   │   │   └── PosSession/
│   │   │       ├── PosSession.php           # Aggregate Root
│   │   │       ├── ValueObject/
│   │   │       │   ├── SessionId.php
│   │   │       │   ├── OrderId.php
│   │   │       │   ├── SessionState.php     # Enum: Idle, Building, Checkout
│   │   │       │   └── OfflineMode.php
│   │   │       ├── Event/
│   │   │       │   ├── SessionStarted.php
│   │   │       │   ├── NewOrderStarted.php
│   │   │       │   ├── OrderParked.php
│   │   │       │   ├── OrderResumed.php
│   │   │       │   ├── OrderDeactivated.php
│   │   │       │   ├── OrderReactivated.php
│   │   │       │   ├── CheckoutInitiated.php
│   │   │       │   ├── PaymentRequested.php
│   │   │       │   ├── OrderCompleted.php
│   │   │       │   ├── OrderCancelledViaPOS.php
│   │   │       │   ├── SessionEnded.php
│   │   │       │   ├── OrderCreatedOffline.php
│   │   │       │   ├── OrderMarkedPendingSync.php
│   │   │       │   └── OrderSyncedOnline.php
│   │   │       └── Repository/
│   │   │           └── PosSessionRepositoryInterface.php
│   │   │
│   │   └── Service/                         # Domain service interfaces (Ports to other BCs)
│   │       ├── OrderingServiceInterface.php
│   │       ├── InventoryServiceInterface.php
│   │       ├── PaymentServiceInterface.php
│   │       ├── DraftLifecycleService.php
│   │       ├── MultiTerminalEnforcementService.php
│   │       └── PendingSyncQueue.php
│   │
│   ├── Application/                         # Use cases and orchestration
│   │   ├── Shared/
│   │   │   └── IdempotencyRegistry.php      # Command idempotency tracking
│   │   │
│   │   ├── Terminal/
│   │   │   ├── Command/
│   │   │   │   ├── RegisterTerminal.php
│   │   │   │   ├── ActivateTerminal.php
│   │   │   │   ├── DisableTerminal.php
│   │   │   │   ├── SetTerminalMaintenance.php
│   │   │   │   └── Handler/
│   │   │   │       ├── RegisterTerminalHandler.php
│   │   │   │       ├── ActivateTerminalHandler.php
│   │   │   │       ├── DisableTerminalHandler.php
│   │   │   │       └── SetTerminalMaintenanceHandler.php
│   │   │   └── ReadModel/
│   │   │       └── TerminalReadModelInterface.php
│   │   │
│   │   ├── Shift/
│   │   │   ├── Command/
│   │   │   │   ├── OpenShift.php
│   │   │   │   ├── CloseShift.php
│   │   │   │   ├── ForceCloseShift.php
│   │   │   │   ├── RecordCashDrop.php
│   │   │   │   └── Handler/
│   │   │   │       ├── OpenShiftHandler.php
│   │   │   │       ├── CloseShiftHandler.php
│   │   │   │       ├── ForceCloseShiftHandler.php
│   │   │   │       └── RecordCashDropHandler.php
│   │   │   └── ReadModel/
│   │   │       └── ShiftReadModelInterface.php
│   │   │
│   │   └── PosSession/
│   │       ├── Command/
│   │       │   ├── StartSession.php
│   │       │   ├── StartNewOrder.php
│   │       │   ├── ParkOrder.php
│   │       │   ├── ResumeOrder.php
│   │       │   ├── ReactivateOrder.php
│   │       │   ├── InitiateCheckout.php
│   │       │   ├── RequestPayment.php
│   │       │   ├── CompleteOrder.php
│   │       │   ├── CancelOrder.php
│   │       │   ├── EndSession.php
│   │       │   ├── StartNewOrderOffline.php
│   │       │   ├── SyncOrderOnline.php
│   │       │   └── Handler/
│   │       │       ├── StartSessionHandler.php
│   │       │       ├── StartNewOrderHandler.php
│   │       │       ├── ParkOrderHandler.php
│   │       │       ├── ResumeOrderHandler.php
│   │       │       ├── ReactivateOrderHandler.php
│   │       │       ├── InitiateCheckoutHandler.php
│   │       │       ├── RequestPaymentHandler.php
│   │       │       ├── CompleteOrderHandler.php
│   │       │       ├── CancelOrderHandler.php
│   │       │       ├── EndSessionHandler.php
│   │       │       ├── StartNewOrderOfflineHandler.php
│   │       │       └── SyncOrderOnlineHandler.php
│   │       └── ReadModel/                   # (reserved for session read model interface)
│   │
│   ├── Infrastructure/                      # Technical implementations (per-context)
│   │   ├── Terminal/
│   │   │   ├── Repository/
│   │   │   │   └── InMemoryTerminalRepository.php
│   │   │   └── ReadModel/
│   │   │       └── InMemoryTerminalReadModel.php
│   │   ├── Shift/
│   │   │   ├── Repository/
│   │   │   │   └── InMemoryShiftRepository.php
│   │   │   └── ReadModel/                   # (reserved for shift read model impl)
│   │   └── PosSession/
│   │       ├── Repository/
│   │       │   └── InMemoryPosSessionRepository.php
│   │       └── ReadModel/                   # (reserved for session read model impl)
│   │
│   └── Shared/                              # POS-specific shared utilities
│       └── Exception/
│           ├── DomainException.php
│           ├── AggregateNotFoundException.php
│           ├── ConcurrencyException.php
│           └── InvariantViolationException.php
│
├── tests/
│   ├── Stub/
│   │   └── Service/
│   │       ├── StubOrderingService.php
│   │       ├── StubInventoryService.php
│   │       └── StubPaymentService.php
│   ├── Unit/
│   │   ├── Application/
│   │   │   └── Shared/
│   │   │       └── IdempotencyRegistryTest.php
│   │   ├── Domain/
│   │   │   ├── Model/
│   │   │   │   ├── Terminal/
│   │   │   │   │   └── TerminalTest.php
│   │   │   │   ├── Shift/
│   │   │   │   │   └── ShiftTest.php
│   │   │   │   └── PosSession/
│   │   │   │       ├── PosSessionTest.php
│   │   │   │       └── PosSessionOfflineTest.php
│   │   │   └── Service/
│   │   │       └── MultiTerminalEnforcementServiceTest.php
│   │   └── Infrastructure/
│   │       └── Terminal/
│   │           ├── InMemoryTerminalRepositoryTest.php
│   │           └── InMemoryTerminalReadModelTest.php
│   ├── Integration/
│   │   ├── CommonLibraryIntegrationTest.php
│   │   ├── ConcurrencyIntegrationTest.php
│   │   ├── DraftLifecycleIntegrationTest.php
│   │   └── OfflineSyncIntegrationTest.php
│   └── Shared/
│       └── Exception/
│           ├── DomainExceptionTest.php
│           ├── AggregateNotFoundExceptionTest.php
│           ├── ConcurrencyExceptionTest.php
│           └── InvariantViolationExceptionTest.php
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
│   ├── demo.md                              # Demo CLI specification
│   ├── agent_workflow.md                    # AI agent guidelines
│   ├── features/
│   │   └── README.md                        # Feature index with status tracking
│   └── raw-discussions/
│       └── 20260218-0324.md                 # Initial POS concept discussion
│
├── .windsurf/
│   └── workflows/
│       └── branch-protection.md             # Branch protection workflow
│
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── phpcs.xml
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
- **Aggregates**: `{Name}.php` (e.g., `Shift.php`, `Terminal.php`)
- **Value Objects**: `{Name}.php` (e.g., `ShiftId.php`, `CashDrop.php`)
- **Enums**: `{Name}.php` (e.g., `ShiftStatus.php`, `SessionState.php`)
- **Events**: `{ActionPastTense}.php` (e.g., `ShiftOpened.php`, `CashDropRecorded.php`)
- **Commands**: `{ActionEntity}.php` — no `Command` suffix (e.g., `OpenShift.php`, `StartSession.php`)
- **Handlers**: `{ActionEntity}Handler.php` (e.g., `OpenShiftHandler.php`, `StartSessionHandler.php`)
- **Interfaces**: `{Name}Interface.php` (e.g., `ShiftRepositoryInterface.php`, `TerminalReadModelInterface.php`)
- **Read Model Implementations**: `InMemory{Name}ReadModel.php` (e.g., `InMemoryTerminalReadModel.php`)
- **Repository Implementations**: `InMemory{Name}Repository.php` (e.g., `InMemoryTerminalRepository.php`)
- **Stubs**: `Stub{Name}.php` in `tests/Stub/` (e.g., `StubOrderingService.php`)

### Namespaces
- Domain Model: `Dranzd\StorebunkPos\Domain\Model\{Context}\`
- Domain Events: `Dranzd\StorebunkPos\Domain\Model\{Context}\Event\`
- Domain Value Objects: `Dranzd\StorebunkPos\Domain\Model\{Context}\ValueObject\`
- Domain Repository Interfaces: `Dranzd\StorebunkPos\Domain\Model\{Context}\Repository\`
- Domain Services: `Dranzd\StorebunkPos\Domain\Service\`
- Application Commands: `Dranzd\StorebunkPos\Application\{Context}\Command\`
- Application Handlers: `Dranzd\StorebunkPos\Application\{Context}\Command\Handler\`
- Application Read Models: `Dranzd\StorebunkPos\Application\{Context}\ReadModel\`
- Infrastructure: `Dranzd\StorebunkPos\Infrastructure\{Context}\{Layer}\`
- Shared Exceptions: `Dranzd\StorebunkPos\Shared\Exception\`
- Test Stubs: `Dranzd\StorebunkPos\Tests\Stub\Service\`

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
