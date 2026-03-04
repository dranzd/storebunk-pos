# Technical Design

## Technology Stack

- **Language**: PHP 8.3+
- **Core Dependencies**: Minimal — only essential libraries
  - `ramsey/uuid: ^4.7` — UUID generation
  - `dranzd/common-event-sourcing: dev-master` — Event sourcing infrastructure
  - `dranzd/common-cqrs: dev-master` — CQRS bus infrastructure
- **Frameworks**: None in core. Framework-agnostic by design.
- **Testing**: PHPUnit 11+
- **Static Analysis**: PHPStan
- **Code Style**: PHP_CodeSniffer (PSR-12)

## Directory Structure

```
src/
├── Domain/           # Pure domain logic (Aggregates, VOs, Events, Repository/Service/ReadModel Interfaces)
├── Application/      # Use cases (Commands, Queries, Handlers, Event Handlers)
├── Infrastructure/   # Adapters (Persistence, ReadModel projections, Service stubs)
└── Shared/           # POS-specific exceptions only (base classes come from common libraries)
```

See [Folder Structure](folder-structure.md) for the complete directory reference.

## Common Libraries (Do NOT Re-implement)

The following are provided by Composer dependencies and must NOT be duplicated in this project:

| Library | Provides | Namespace |
|---------|----------|-----------|
| `dranzd/common-event-sourcing` | `AggregateRoot`, `AggregateRootTrait`, `AggregateEvent`, `AbstractAggregateEvent`, `EventStore`, `InMemoryEventStore`, `AggregateRootRepository` | `Dranzd\Common\EventSourcing\Domain\EventSourcing\` |
| `dranzd/common-cqrs` | `Command`, `AbstractCommand`, `Query`, `AbstractQuery`, `Event`, `AbstractEvent`, `SimpleCommandBus`, `SimpleQueryBus`, `SimpleEventBus`, `InMemoryHandlerRegistry` | `Dranzd\Common\Cqrs\` |
| `dranzd/common-valueobject` | `ValueObject`, `Uuid`, `Money\Basic`, `Literal`, `Integer`, `Collection`, `DateTime`, `Actor` | `Dranzd\Common\Domain\ValueObject\` |
| `dranzd/common-domain-assert` | `Assertion` | `Dranzd\Common\Domain\Assert\` |
| `dranzd/common-utils` | `ArrayUtil`, `DateUtil`, `MoneyUtil`, `StringUtil` | `Dranzd\Common\Utils\` |

POS aggregates implement `AggregateRoot` and use `AggregateRootTrait` from `common-event-sourcing`. POS events extend `AbstractAggregateEvent`. POS value objects (e.g., `TerminalId`, `ShiftId`) extend `Uuid` from `common-valueobject`. POS commands extend `AbstractCommand` from `common-cqrs`.

## Coding Standards

## @standard: php-coding-standards
@category: architecture
@status: stable

All code must follow PSR-12 style, use strict types, maintain immutability for VOs and events, avoid public getters on aggregates, and include PHPDoc for all public methods. Event accessors use get/is prefixes, properties are private (not readonly), and public methods are final by default.

- **PSR-12**: Code style enforced via PHP_CodeSniffer.
- **Strict Types**: `declare(strict_types=1);` in all files.
- **Immutability**: All Value Objects and Events are immutable.
- **No Public Getters on Aggregates**: All reads go through CQRS projections.
- **PHPDoc**: All public methods must have PHPDoc blocks.
- **Event Accessor Naming**: All domain event getter methods use the `get` prefix (e.g., `getTerminalId()`, `getShiftId()`). Boolean accessors use the `is` prefix (e.g., `isActive()`). See [ADR-001](adr/001-event-getter-prefix.md) for the full rationale.
- **Event Properties**: All domain event properties are `private` (never `public readonly`) to avoid PHPStan `property.readOnlyAssignNotInConstructor` errors. See [ADR-001](adr/001-event-getter-prefix.md).
- **`final` Methods**: All public methods on concrete classes are declared `final` by default.

## Namespace Convention

```
Dranzd\StorebunkPos\Domain\Model\{Context}\...
Dranzd\StorebunkPos\Domain\Repository\...
Dranzd\StorebunkPos\Domain\ReadModel\...
Dranzd\StorebunkPos\Domain\Service\...
Dranzd\StorebunkPos\Application\Command\{Context}\...
Dranzd\StorebunkPos\Application\Query\{Context}\...
Dranzd\StorebunkPos\Application\EventHandler\...
Dranzd\StorebunkPos\Infrastructure\Persistence\...
Dranzd\StorebunkPos\Shared\...
```

## Integration Points

## @standard: bus-integration-pattern
@category: architecture
@status: stable

The core must expose Command Bus for writes, Query Bus for reads, and Event Bus for side effects. External BCs are accessed through Service Interfaces (Ports) where consumers provide real adapters.

- The core exposes a **Command Bus** for write operations.
- The core exposes a **Query Bus** for read operations.
- Events are dispatched to an **Event Bus** for side effects and projections.
- External BCs are accessed through **Service Interfaces (Ports)** — consumers provide real adapters.

## Adapters

While the core is agnostic, consumers can build adapters for:

1. **Laravel**: Service Providers, Facades, and Artisan commands to run a POS instance.
2. **Symfony**: Bundle integration for Symfony-based applications.
3. **Storebunk Ecommerce**: Integration to sync catalog and inventory.

The library provides **in-memory implementations** for all ports (repositories, read models, service interfaces) for testing purposes.

## Event Publishing

## @standard: event-publishing-separation
@category: architecture
@status: stable

Event publishing is NOT provided by the core library. Consumers must implement their own event publishing strategy (Laravel Events, Symfony EventDispatcher, RabbitMQ, etc.). Repository implementations are responsible for dispatching events after persisting aggregates.

Event publishing is **NOT provided** by this library. Consumers implement their own event publishing strategy:

- Laravel Events
- Symfony EventDispatcher
- RabbitMQ / Kafka
- Custom event bus

Repository implementations are responsible for dispatching events after persisting aggregates.

## Quality Tools

```bash
./utils test          # Run PHPUnit tests
./utils phpstan       # Run PHPStan static analysis
./utils cs-check      # Check code style with PHPCS
./utils cs-fix        # Fix code style with PHPCBF
./utils quality       # Run all quality checks
```
