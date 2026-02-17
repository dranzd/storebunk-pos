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
└── Shared/           # Base classes, exceptions, common utilities
```

See [Folder Structure](folder-structure.md) for the complete directory reference.

## Coding Standards

- **PSR-12**: Code style enforced via PHP_CodeSniffer.
- **Strict Types**: `declare(strict_types=1);` in all files.
- **Immutability**: All Value Objects and Events are immutable.
- **No Public Getters on Aggregates**: All reads go through CQRS projections.
- **PHPDoc**: All public methods must have PHPDoc blocks.

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
