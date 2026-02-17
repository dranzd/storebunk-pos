# Tasks

This file tracks the detailed tasks for the current active milestone.

See [Milestones](milestones.md) for the full roadmap and [Features](features/README.md) for the complete checklist.

---

## Current Milestone: Phase 1 — Foundation (Features 1001-1005)

### 1001 - Base Classes
- [ ] Refactor `AggregateRoot` abstract class (align with common-event-sourcing)
- [ ] Refactor `DomainEvent` interface (align with common-event-sourcing)
- [ ] Refactor `ValueObject` base class
- [ ] Update namespace to `Dranzd\StorebunkPos\...`

### 1002 - Event Store Interface
- [ ] Define `EventStoreInterface`
- [ ] Implement `InMemoryEventStore` for testing

### 1003 - CQRS Bus Integration
- [ ] Add `dranzd/common-cqrs` dependency
- [ ] Add `dranzd/common-event-sourcing` dependency
- [ ] Verify command bus and query bus integration

### 1004 - Shared Value Objects
- [ ] Implement `Money` value object
- [ ] Implement `BranchId` value object
- [ ] Implement `CashierId` value object

### 1005 - Exception Hierarchy
- [ ] Implement `DomainException`
- [ ] Implement `AggregateNotFoundException`
- [ ] Implement `InvariantViolationException`
- [ ] Implement `ConcurrencyException`

### Infrastructure
- [ ] Fix `composer.json` (autoload, dependencies, PHP 8.3)
- [ ] Set up PHPStan configuration
- [ ] Set up PHP_CodeSniffer configuration
- [ ] Set up basic architectural tests (ensure Domain doesn't depend on Infra)

---

## Upcoming: Phase 2 — Terminal Aggregate (Features 2001-2004)

_Not started. See [Milestones](milestones.md) for details._
