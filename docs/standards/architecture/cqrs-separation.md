<!-- hash: e7698f07fd652d29323be92573dfe67aa22789988c477e16955ed82dde4afd86 -->
# cqrs-separation

Category: architecture
Status: stable
Source: storebunk-pos

---

Separate models must exist for reads and writes. Write model uses aggregates with event store. Read model uses projections for fast queries. All read model interfaces must follow Interface Segregation Principle.

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

---

## Source File
docs/architecture.md
