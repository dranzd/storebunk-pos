# Folder Structure Reference

## Complete Directory Tree

```
storebunk-pos/
├── src/
│   ├── Domain/                              # Core business logic
│   │   ├── Event/
│   │   │   ├── BaseAggregateEvent.php       # Envelope every POS event extends
│   │   │   └── DomainEventInterface.php     # POS marker interface for domain events
│   │   │
│   │   ├── Model/                           # Domain models (per-context)
│   │   │   ├── Terminal/
│   │   │   │   ├── Terminal.php             # Aggregate Root
│   │   │   │   ├── ValueObject/
│   │   │   │   │   ├── TerminalId.php
│   │   │   │   │   ├── BranchId.php
│   │   │   │   │   └── TerminalStatus.php   # Enum: Active, Disabled, Maintenance, Decommissioned
│   │   │   │   ├── Event/
│   │   │   │   │   ├── TerminalRegistered.php
│   │   │   │   │   ├── TerminalActivated.php
│   │   │   │   │   ├── TerminalDisabled.php
│   │   │   │   │   ├── TerminalMaintenanceSet.php
│   │   │   │   │   ├── TerminalRenamed.php
│   │   │   │   │   ├── TerminalReassigned.php
│   │   │   │   │   ├── TerminalDecommissioned.php
│   │   │   │   │   └── TerminalRecommissioned.php
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
│   │   │   │   │   ├── ShiftAssigned.php
│   │   │   │   │   ├── ShiftUnassigned.php
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
│   │   │       │   ├── CashierId.php        # The session's operating cashier
│   │   │       │   ├── SessionState.php     # Enum: Idle, Building, Checkout, Payment
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
│   │       ├── MultiTerminalEnforcementService.php  # The occupancy RULES
│   │       ├── ShiftSlotReservationInterface.php    # Atomic uniqueness authority (port)
│   │       ├── ShiftSlotBook.php                    # Slot bookkeeping shared by impls
│   │       ├── ShiftClosePolicy.php
│   │       └── PendingSyncQueue.php
│   │
│   ├── Application/                         # Use cases and orchestration
│   │   ├── Shared/
│   │   │   ├── IdempotencyRegistry.php      # Command idempotency tracking
│   │   │   └── OfflineStateReplay.php       # Rebuilds queue + registry from events
│   │   │
│   │   ├── Terminal/
│   │   │   ├── Command/
│   │   │   │   ├── RegisterTerminal.php
│   │   │   │   ├── ActivateTerminal.php
│   │   │   │   ├── DisableTerminal.php
│   │   │   │   ├── SetTerminalMaintenance.php
│   │   │   │   ├── RenameTerminal.php
│   │   │   │   ├── ReassignTerminal.php
│   │   │   │   ├── DecommissionTerminal.php
│   │   │   │   ├── RecommissionTerminal.php
│   │   │   │   └── Handler/
│   │   │   │       ├── RegisterTerminalHandler.php
│   │   │   │       ├── ActivateTerminalHandler.php
│   │   │   │       ├── DisableTerminalHandler.php
│   │   │   │       ├── SetTerminalMaintenanceHandler.php
│   │   │   │       ├── RenameTerminalHandler.php
│   │   │   │       ├── ReassignTerminalHandler.php
│   │   │   │       ├── DecommissionTerminalHandler.php
│   │   │   │       └── RecommissionTerminalHandler.php
│   │   │   └── ReadModel/
│   │   │       └── TerminalReadModelInterface.php
│   │   │
│   │   ├── Shift/
│   │   │   ├── Command/
│   │   │   │   ├── OpenShift.php
│   │   │   │   ├── AssignShift.php
│   │   │   │   ├── UnassignShift.php
│   │   │   │   ├── CloseShift.php
│   │   │   │   ├── ForceCloseShift.php
│   │   │   │   ├── RecordCashDrop.php
│   │   │   │   └── Handler/
│   │   │   │       ├── OpenShiftHandler.php
│   │   │   │       ├── AssignShiftHandler.php
│   │   │   │       ├── UnassignShiftHandler.php
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
│   │       │   ├── DeactivateOrder.php
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
│   │       │       ├── DeactivateOrderHandler.php
│   │       │       ├── InitiateCheckoutHandler.php
│   │       │       ├── RequestPaymentHandler.php
│   │       │       ├── CompleteOrderHandler.php
│   │       │       ├── CancelOrderHandler.php
│   │       │       ├── EndSessionHandler.php
│   │       │       ├── StartNewOrderOfflineHandler.php
│   │       │       └── SyncOrderOnlineHandler.php
│   │       └── ReadModel/
│   │           └── PosSessionReadModelInterface.php
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
│   │   │   ├── Reservation/
│   │   │   │   └── InMemoryShiftSlotReservation.php  # Single-process reference impl
│   │   │   └── ReadModel/
│   │   │       └── InMemoryShiftReadModel.php
│   │   └── PosSession/
│   │       ├── Repository/
│   │       │   └── InMemoryPosSessionRepository.php
│   │       └── ReadModel/
│   │           └── InMemoryPosSessionReadModel.php
│   │
│   └── Shared/                              # POS-specific shared utilities
│       └── Exception/
│           ├── DomainException.php
│           ├── AggregateNotFoundException.php
│           ├── ConcurrencyException.php
│           ├── SlotCleanupFailedException.php
│           └── InvariantViolationException.php
│
├── demo/                                    # Runnable CLI demo (see docs/demo.md)
│   ├── demo                                 # Entry point
│   ├── bootstrap.php                        # Wires repositories, buses, stubs, stores
│   ├── README.md
│   ├── cli/
│   │   ├── Output.php
│   │   ├── CliArgs.php
│   │   ├── StateStore.php                   # JSON id state, lock + atomic rename
│   │   ├── FileEventStore.php               # JSON event store, refuses a taken version
│   │   ├── FileShiftSlotReservation.php     # Cross-process shift-slot claims
│   │   ├── DemoReset.php                    # All-or-nothing reset of the three stores
│   │   ├── TerminalProjection.php
│   │   └── services/                        # terminal.php, shift.php, session.php
│   ├── scenarios/                           # 01–07 end-to-end shell walkthroughs
│   └── data/                                # Runtime state (git-ignored)
│
├── tests/
│   ├── Integration/
│   │   ├── CommonLibraryIntegrationTest.php
│   │   ├── ConcurrencyIntegrationTest.php
│   │   ├── DemoCliMalformedHistoryTest.php
│   │   ├── DemoCliRecoveryTest.php
│   │   ├── DemoCliShiftCloseGuardTest.php
│   │   ├── DemoCliShiftOpenRaceTest.php
│   │   ├── DraftLifecycleIntegrationTest.php
│   │   └── OfflineSyncIntegrationTest.php
│   ├── Stub/
│   │   ├── Repository/
│   │   │   ├── CallbackFailingShiftRepository.php
│   │   │   └── InterleavingShiftRepository.php
│   │   ├── Reservation/
│   │   │   └── ReleaseFailingSlotReservation.php
│   │   └── Service/
│   │       ├── StubInventoryService.php
│   │       ├── StubOrderingService.php
│   │       └── StubPaymentService.php
│   └── Unit/
│       ├── Application/
│       │   ├── PosSession/
│       │   │   ├── Handler/
│       │   │   │   ├── CancelOrderHandlerTest.php
│       │   │   │   ├── CompleteOrderHandlerTest.php
│       │   │   │   ├── DeactivateOrderHandlerTest.php
│       │   │   │   ├── EndSessionHandlerTest.php
│       │   │   │   ├── InitiateCheckoutHandlerTest.php
│       │   │   │   ├── ParkOrderHandlerTest.php
│       │   │   │   ├── RequestPaymentHandlerTest.php
│       │   │   │   ├── ResumeOrderHandlerTest.php
│       │   │   │   └── StartSessionHandlerTest.php
│       │   │   └── OrderTerminalBindingTest.php
│       │   ├── Shared/
│       │   │   └── IdempotencyRegistryTest.php
│       │   ├── Shift/
│       │   │   └── Handler/
│       │   │       ├── AssignShiftHandlerTest.php
│       │   │       ├── CloseShiftHandlerTest.php
│       │   │       ├── ForceCloseShiftHandlerTest.php
│       │   │       ├── OpenShiftHandlerTest.php
│       │   │       ├── RecordCashDropHandlerTest.php
│       │   │       └── UnassignShiftHandlerTest.php
│       │   └── Terminal/
│       │       └── Handler/
│       │           ├── ActivateTerminalHandlerTest.php
│       │           ├── DecommissionTerminalHandlerTest.php
│       │           ├── DisableTerminalHandlerTest.php
│       │           ├── ReassignTerminalHandlerTest.php
│       │           ├── RecommissionTerminalHandlerTest.php
│       │           ├── RegisterTerminalHandlerTest.php
│       │           ├── RenameTerminalHandlerTest.php
│       │           └── SetTerminalMaintenanceHandlerTest.php
│       ├── Demo/
│       │   ├── DemoResetTest.php
│       │   ├── FileEventStoreTest.php
│       │   ├── FileShiftSlotReservationTest.php
│       │   └── StateStoreTest.php
│       ├── Domain/
│       │   ├── Event/
│       │   │   └── PayloadContractTest.php
│       │   ├── Model/
│       │   │   ├── PosSession/
│       │   │   │   ├── PosSessionOfflineTest.php
│       │   │   │   └── PosSessionTest.php
│       │   │   ├── Shift/
│       │   │   │   └── ShiftTest.php
│       │   │   └── Terminal/
│       │   │       └── TerminalTest.php
│       │   └── Service/
│       │       ├── MultiTerminalEnforcementServiceTest.php
│       │       └── ShiftClosePolicyTest.php
│       ├── Infrastructure/
│       │   ├── PosSession/
│       │   │   └── InMemoryPosSessionReadModelTest.php
│       │   ├── Shift/
│       │   │   ├── InMemoryShiftReadModelTest.php
│       │   │   └── InMemoryShiftSlotReservationTest.php
│       │   └── Terminal/
│       │       ├── InMemoryTerminalReadModelTest.php
│       │       └── InMemoryTerminalRepositoryTest.php
│       └── Shared/
│           └── Exception/
│               ├── AggregateNotFoundExceptionTest.php
│               ├── ConcurrencyExceptionTest.php
│               ├── DomainExceptionTest.php
│               └── InvariantViolationExceptionTest.php
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
│   ├── reported-issues/                     # Issue tracking system
│   │   ├── README.md                        # Issue standards and template
│   │   ├── open-issues.md                   # Active issues checklist
│   │   ├── 2000-terminal/
│   │   ├── 3000-shift/
│   │   ├── 6000-bc-integration/
│   │   ├── 8000-concurrency/
│   │   └── 9000-offline-sync/
│   ├── library-feedback/                    # Common library feedback tracking
│   │   ├── README.md
│   │   └── open-feedback.md
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

## @standard: domain-layer-purity
@category: architecture
@status: stable

The domain layer must contain only pure business logic with no framework dependencies. It should encapsulate aggregates, value objects, events, repository interfaces, and service interfaces that define business contracts.

### Domain Layer
- **Pure business logic** — No framework dependencies
- **Aggregates** enforce business rules and invariants
- **Value Objects** are immutable and self-validating
- **Events** represent facts that happened
- **Repository Interfaces** define contracts without implementation
- **Service Interfaces** define ports to external bounded contexts
- **Read Model Interfaces** define query contracts

## @standard: hexagonal-architecture
@category: architecture
@status: stable

Application layer orchestrates domain objects to fulfill use cases without containing business logic. Infrastructure layer implements technology-specific adapters for domain ports. This maintains dependency inversion and technology independence.

### Application Layer
- **Orchestrates** domain objects to fulfill use cases
- **Commands** represent intentions to change state
- **Queries** represent requests for data
- **Handlers** execute commands and queries
- **Event Handlers** react to domain events for cross-aggregate coordination

## @standard: repository-interface-separation
@category: architecture
@status: stable

Repository interfaces must be defined in the domain layer while implementations live in infrastructure. Domain code depends only on interfaces, never concrete implementations.

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

## @standard: event-sourcing-naming
@category: event-sourcing
@status: stable

Domain events must be named in past tense using the {ActionPastTense}.php pattern. Events represent facts that happened in the domain and should be immutable.

## @standard: command-naming-convention
@category: architecture
@status: stable

Commands must use {ActionEntity}.php naming without Command suffix. Commands represent intentions to change state and should be simple data structures.

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

## @standard: aggregate-context-organization
@category: ddd
@status: stable

Domain models must be organized by bounded context with each context containing its aggregate, value objects, events, and repository interfaces. This maintains context boundaries and model consistency.

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
4. Create repository interface in `src/Domain/Model/{Context}/Repository/`
5. Create read model interface in `src/Application/{Context}/ReadModel/`
6. Implement in-memory repository in `src/Infrastructure/{Context}/Repository/`
7. Implement in-memory projection in `src/Infrastructure/{Context}/ReadModel/`

### New Use Case
1. Create command in `src/Application/{Context}/Command/`
2. Create handler in `src/Application/{Context}/Command/Handler/`
3. Wire up in the host's dependency injection container

### New External BC Integration
1. Create service interface in `src/Domain/Service/`
2. Implement stub adapter in `tests/Stub/Service/`
3. Consumer provides real adapter
