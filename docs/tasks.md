# Tasks

This file tracks the detailed tasks for the current active milestone.

See [Milestones](milestones.md) for the full roadmap and [Features](features/README.md) for the complete checklist.

---

## ✓ ALL PHASES COMPLETE — StoreBunk POS Implementation Finished

**109 tests, 230 assertions. PHPStan + PHPCS clean.**

| Phase | Status |
|-------|--------|
| Phase 1 — Foundation | ✓ COMPLETE |
| Phase 2 — Terminal Aggregate | ✓ COMPLETE |
| Phase 3 — Shift Aggregate | ✓ COMPLETE |
| Phase 4 — PosSession Aggregate | ✓ COMPLETE |
| Phase 5 — Checkout + Payment + BC Ports | ✓ COMPLETE |
| Phase 6 — Draft Lifecycle + Reservation | ✓ COMPLETE |
| Phase 7 — Multi-Terminal + Concurrency | ✓ COMPLETE |
| Phase 8 — Offline + Sync | ✓ COMPLETE |

See [Milestones](milestones.md) for full details on each phase.

---

## Phase 1 — Foundation (Features 1001-1005) ✓ COMPLETE

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

### 1005 - Tests ✓
- [x] Unit tests for exception hierarchy
- [x] Integration tests verifying common library usage patterns
