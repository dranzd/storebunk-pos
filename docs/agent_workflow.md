# Agent Workflow / Guidelines

This document serves as a guide for AI agents working on the StoreBunk POS project.

---

## Your Role

You are helping build a **library-first, framework-agnostic** POS system. Your primary focus is on correctness, adherence to DDD/ES/CQRS patterns, and clean hexagonal architecture.

---

## Core Principles

1. **Task-Driven Development** — No code is written without a corresponding task
2. **Specification First** — Tasks must exist and be reviewed before implementation
3. **Update Before Implement** — If design changes are needed, update the task first
4. **Document Everything** — All decisions and changes must be documented
5. **Maintain History** — Original specifications are preserved, changes tracked separately

---

## Rules of Engagement

1. **Read the Docs**: Before writing code, consult `core_design.md`, `architecture.md`, `domain-model.md`, and `technical_design.md` to understand where your code belongs.
2. **No UI Logic**: Do not introduce Blade templates, HTML, or Controllers into `src/Domain` or `src/Application`. Those belong in specific UI adapters or separate apps consuming this library.
3. **Strict Typing**: Always use `declare(strict_types=1);` in all files.
4. **Test First**: When possible, write a failing test for the behavior you are about to implement.
5. **No Public Getters on Aggregates**: All reads go through CQRS projections, never through aggregate getters.
6. **Test via Events/Projections**: Tests must assert via emitted events or projection state, not aggregate getters.
7. **Branch Protection**: Always check the current branch before making code changes. See `.windsurf/workflows/branch-protection.md`.

---

## Architecture Checklist

- [ ] **Domain**: Does this entity rely on framework classes? (It shouldn't)
- [ ] **Events**: Did I capture the state change in a strict Domain Event?
- [ ] **Persistence**: Am I using the Repository interface, not a concrete implementation?
- [ ] **Read Model**: Am I querying through a ReadModel interface, not aggregate getters?
- [ ] **Service Ports**: Am I using service interfaces for external BC calls?
- [ ] **Immutability**: Are Value Objects and Events immutable?
- [ ] **Strict Types**: Does every file have `declare(strict_types=1);`?

---

## Implementation Process

### Phase 1: Task Review

Before any implementation begins:

1. Read the feature specification (`docs/features/README.md`)
2. Identify the specific task to be implemented
3. Review task requirements thoroughly
4. Check dependencies — ensure prerequisite tasks are complete

### Phase 2: Task Validation

If the task needs changes:

1. **STOP implementation immediately**
2. Document the issue
3. Update the task specification FIRST
4. Only then proceed to implementation

### Phase 3: Implementation

Only after task is validated:

1. Follow the task specification exactly
2. Implement all subtasks
3. Write tests as specified
4. Document code with PHPDoc
5. Follow coding standards (PSR-12, strict types)

### Phase 4: Documentation

After implementation is complete:

1. Check off task items in the specification
2. Add status note at the top of the task section
3. Update `docs/milestones.md` progress
4. Update `docs/features/README.md` status
5. Commit with the suggested commit message from milestones

---

## Critical Implementation Rules

### Common Library Usage

All POS aggregates, events, commands, queries, and value objects extend classes from the common libraries. **Do NOT re-implement base classes.**

```php
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRoot;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRootTrait;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\Common\Domain\ValueObject\Identity\Uuid;
use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

// Aggregate: implements AggregateRoot interface, uses AggregateRootTrait
final class Shift implements AggregateRoot
{
    use AggregateRootTrait;
    // ... business logic, NO public getters
}

// Event: extends AbstractAggregateEvent from common-event-sourcing
final class ShiftOpened extends AbstractAggregateEvent { }

// Value Object: extends Uuid from common-valueobject
final class ShiftId extends Uuid { }

// Command: extends AbstractCommand from common-cqrs
final class OpenShiftCommand extends AbstractCommand { }
```

### Aggregate Roots: NO Public Getters

```php
// WRONG: Public getters on aggregate
final class Shift implements AggregateRoot
{
    public function getShiftId(): ShiftId { } // FORBIDDEN!
    public function getStatus(): ShiftStatus { } // FORBIDDEN!
}

// CORRECT: Query through projection
final class InMemoryShiftProjection implements ShiftReadModel
{
    public function getShift(ShiftId $shiftId): ?array { }
    public function getShiftCashSummary(ShiftId $shiftId): ?array { }
}
```

### Testing Without Getters

```php
// WRONG: Testing via getters
$shift = Shift::open(...);
$this->assertEquals('open', $shift->getStatus()->toString()); // BAD!

// CORRECT: Testing via events (using popRecordedEvents from AggregateRootTrait)
$shift = Shift::open(...);
$events = $shift->popRecordedEvents();
$event = $this->findEvent(ShiftOpened::class, $events);
$this->assertEquals($cashierId, $event->cashierId); // GOOD!

// CORRECT: Testing via projection
$projection = new InMemoryShiftProjection();
$projection->onShiftOpened($event);
$data = $projection->getShift($shiftId);
$this->assertEquals('open', $data['status']); // GOOD!
```

---

## File Locations

```
docs/features/README.md          # Feature specifications and status
docs/milestones.md               # Phased roadmap with commit messages
docs/tasks.md                    # Active task tracking
docs/domain-model.md             # Aggregates, Commands, Events, Policies
docs/architecture.md             # Full architecture documentation
docs/folder-structure.md         # Directory reference
```

---

## Commit Convention

Use conventional commits with scope:

```
feat(scope): description
fix(scope): description
docs(scope): description
test(scope): description
chore(scope): description
```

Scopes: `foundation`, `terminal`, `shift`, `session`, `checkout`, `draft-lifecycle`, `concurrency`, `offline`

---

## Anti-Patterns to Avoid

- Implementing without a task
- Modifying original task descriptions (use addendums)
- Implementing differently than specified without updating the task
- Skipping documentation
- Continuing when design does not fit
- Adding public getters to aggregates
- Testing via aggregate state instead of events/projections
- Introducing framework dependencies in Domain or Application layers
