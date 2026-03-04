<!-- hash: 43425da1243160cf15c8ae40fbefe0e91802fce4d8ece1ba09f7871411fd9b09 -->
# domain-layer-isolation

Category: architecture
Status: stable
Source: storebunk-pos

---

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

---

## Source File
docs/core_design.md
