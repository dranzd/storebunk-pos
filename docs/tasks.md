# Tasks

This file tracks the detailed tasks for the current active milestone.

See [Milestones](milestones.md) for the full roadmap and [Features](features/README.md) for the complete checklist.

---

## Current Milestone: Phase 1 — Foundation (Features 1001-1005) ✓ COMPLETE

> **Important:** Base classes (`AggregateRoot`, `AggregateRootTrait`, `AggregateEvent`, `EventStore`, `InMemoryEventStore`, `AggregateRootRepository`), CQRS infrastructure (`Command`, `Query`, buses, handler registry), and value object primitives (`ValueObject`, `Uuid`, `Money\Basic`) are provided by common libraries. Do NOT re-implement them.

### 1001 - Project Setup ✓
- [x] Fix `composer.json` (autoload, dependencies, PHP 8.3)
- [x] Docker environment (Dockerfile, docker-compose.yml, utils script)
- [x] PHPUnit configuration
- [x] PHPStan configuration
- [x] PHP_CodeSniffer configuration

### 1002 - Common Library Verification ✓
- [x] Verify `dranzd/common-event-sourcing` integration (AggregateRoot, AggregateRootTrait, EventStore, InMemoryEventStore)
- [x] Verify `dranzd/common-cqrs` integration (SimpleCommandBus, SimpleQueryBus, SimpleEventBus, InMemoryHandlerRegistry)
- [x] Verify `dranzd/common-valueobject` integration (Uuid, Money\Basic, ValueObject interface)
- [x] Write integration smoke tests confirming libraries work together

### 1003 - POS-Specific Exception Hierarchy ✓
- [x] Implement `DomainException` (`src/Shared/Exception/`)
- [x] Implement `AggregateNotFoundException`
- [x] Implement `InvariantViolationException`
- [x] Implement `ConcurrencyException`

### 1004 - POS Domain Event Interface ✓
- [x] Create `DomainEventInterface` marker interface (`src/Domain/Event/`)
- [x] Document how POS events extend `AbstractAggregateEvent` from common-event-sourcing

### 1005 - Tests ✓
- [x] Unit tests for exception hierarchy
- [x] Integration tests verifying common library usage patterns
- [x] Ensure Domain layer has no Infrastructure dependencies

---

## Upcoming: Phase 2 — Terminal Aggregate (Features 2001-2004)

_Not started. See [Milestones](milestones.md) for details._
