<!-- hash: d48d31ef5fe2d405293cbaaac0d0af58935b10adc4ddc05db7cca2395869163d -->
# repository-interface-separation

Category: architecture
Status: stable
Source: storebunk-pos

---

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

---

## Source File
docs/folder-structure.md
