# ADR-001: Event Property Encapsulation and `get`-Prefixed Accessor Methods

## @standard: event-encapsulation-pattern
@category: event-sourcing
@status: stable

All domain events must use private properties (not readonly) with public get-prefixed getter methods. This avoids PHPStan conflicts and provides stable abstraction for consumers. All public methods on event classes must be declared final.

**Status:** Accepted
**Date:** 2026-03-02
**Applies to:** All domain events across all bounded contexts in `src/Domain/Model/*/Event/`

---

## Context

Domain events in an event-sourced system are the immutable facts that represent state changes. They are consumed by multiple parties:

- **Aggregate roots** (`Terminal`, `Shift`, `PosSession`) — apply events to rebuild internal state during reconstitution.
- **Read models** (`InMemoryTerminalReadModel`, `InMemoryPosSessionReadModel`) — project events into query-optimized views.
- **Integration listeners** — forward events to other bounded contexts (e.g., Ordering BC, Inventory BC).
- **Tests** — assert on event contents after aggregate operations.

As the system evolves, event internals may change: properties may be renamed, types may shift from primitives to value objects, or structural representations may be refactored. Any such change risks breaking all consumers simultaneously if they depend on the event's internal structure.

Additionally, PHP's `readonly` properties — while enforcing immutability — cause PHPStan `property.readOnlyAssignNotInConstructor` errors when assigned in static factory methods (`occur()`) or reconstitution methods (`setPayload()` / `fromArray()`), since those assignments happen outside the constructor. Suppressing these errors via `@phpstan-ignore` annotations or `phpstan.neon` exclusions is undesirable because it hides real issues and sets a bad precedent.

### Cross-Bounded-Context Evidence: `storebunk-sales-order` and `storebunk-inventory`

The `readonly` property conflict is not theoretical. In the sibling bounded contexts `storebunk-sales-order` and `storebunk-inventory`, event classes originally used `public readonly` properties for event data (e.g., `public readonly string $salesOrderId`). This pattern caused two concrete problems:

1. **PHPStan `property.readOnlyAssignNotInConstructor` errors** — The `readonly` modifier forbids assignment outside the constructor. However, event classes in an event-sourced system require assignment in the static `occur()` factory and in the `setPayload()` (or `fromArray()`) reconstitution method. PHPStan v1.x correctly detects these as violations even when the assignment is within the same class scope for static methods that return `self`. The only workarounds were either suppressions or restructuring to private properties with getters.

2. **Shotgun surgery on type changes** — When event data migrated from `string $salesOrderId` to a `SalesOrderId` value object, every consumer that accessed `$event->salesOrderId` directly required simultaneous updates. The `public readonly` surface area became a hidden dependency contract with no stable abstraction layer between the event internals and its consumers.

Both issues were resolved in those projects by adopting `private` properties with `get`-prefixed public getter methods — the same pattern mandated by this ADR.

---

## Decision

## @standard: event-getter-naming
@category: event-sourcing
@status: stable

All domain events must use private properties with get-prefixed public getter methods. Boolean accessors use is prefix. Properties are not readonly to avoid PHPStan conflicts with static factory methods and reconstitution.

All domain events in this project use the following encapsulation pattern:

### 1. Private Properties (Not Readonly)

Properties are declared `private` with explicit types. They are **not** `readonly` — this avoids PHPStan conflicts with assignment in `occur()` and `fromArray()`, while still preventing external mutation since there are no setters.

```php
private TerminalId $terminalId;
private string $name;
private DateTimeImmutable $registeredAt;
```

### 2. Public `get`-Prefixed Getter Methods (Declared `final`)

Every property is exposed via a public getter with a `get` prefix. This is the **only** way consumers access event data. All public methods on event classes are declared `final` per project convention.

```php
final public function getTerminalId(): TerminalId
{
    return $this->terminalId;
}

final public function getName(): string
{
    return $this->name;
}
```

The `get` prefix is required for event and value-object accessors. Boolean accessors use the `is` prefix (e.g., `isActive()`).

### 3. Static `occur()` Factory

Events are constructed via a named static factory method. The private constructor prevents direct instantiation with `new`.

```php
final public static function occur(
    TerminalId $terminalId,
    string $name,
    DateTimeImmutable $registeredAt
): self {
    $event = new self();
    $event->terminalId = $terminalId;
    $event->name = $name;
    $event->registeredAt = $registeredAt;
    return $event;
}
```

### 4. `toArray()` / `fromArray()` for Serialization

These methods handle the serialization contract with the event store. They operate on private properties directly from within the class scope.

```php
final public function toArray(): array
{
    return [
        'terminal_id' => $this->terminalId->toNative(),
        'name' => $this->name,
        'registered_at' => $this->registeredAt->format(DATE_ATOM),
    ];
}

/**
 * @param array<string, mixed> $array
 */
final public static function fromArray(array $array): static
{
    $event = parent::fromArray($array);
    $event->terminalId = TerminalId::fromNative($array['payload']['terminal_id']);
    $event->name = $array['payload']['name'];
    $event->registeredAt = new DateTimeImmutable($array['payload']['registered_at']);
    return $event;
}
```

---

## Rationale

### Consumer Protection from Internal Changes

This is the primary driver. When consumers call `$event->getTerminalId()` instead of accessing `$event->terminalId`, the event class can:

- **Rename the internal property** without breaking consumers (the getter stays the same).
- **Change the internal type** (e.g., from `string` to a `TerminalId` value object) while keeping the getter's return type stable, or evolving it with a deprecation period.
- **Derive values** — a getter can compute or transform data rather than returning a raw property, without consumers knowing.

With `public readonly` properties, **every** consumer is coupled to the exact property name and type. A single rename or type change requires shotgun surgery across the aggregate, projections, listeners, and tests.

### Static Analysis Compliance

`readonly` properties cannot be assigned outside the constructor in PHP 8.1+. PHPStan correctly flags this. The alternatives — suppressing the error via `@phpstan-ignore` annotations or `phpstan.neon` exclusions — hide real bugs and violate the principle of keeping the static analysis baseline clean.

With `private` (non-readonly) properties, assignments in `occur()` and `fromArray()` are perfectly legal. Immutability is enforced by the absence of public setters and the private constructor preventing arbitrary instantiation.

### Expressive Construction

`Event::occur(...)` reads as a domain sentence — "a TerminalRegistered event occurred with these parameters." This is more expressive than `new TerminalRegistered(...)`.

### Preparation for Value Object Migration

The project roadmap includes migrating event properties from primitives to value objects. With getters in place, this migration becomes non-breaking:

```php
// Before: returns string
final public function getTerminalId(): string { return $this->terminalId; }

// After: returns VO (breaking change is visible and intentional)
final public function getTerminalId(): TerminalId { return $this->terminalId; }
```

Without getters, switching from `public string $terminalId` to `public TerminalId $terminalId` would silently break every consumer that expected a string.

---

## Pattern Summary

| Aspect | Rule |
|--------|------|
| **Properties** | `private` typed, no `readonly` |
| **Access** | Public `get`-prefixed getters, all declared `final` |
| **Boolean access** | `is`-prefixed (e.g., `isActive()`) |
| **Construction** | Static `occur()` factory method |
| **Serialization** | `toArray()` and `fromArray()` on private properties |
| **PHPStan suppression** | None — the pattern is fully compliant |

---

## Consequences

### Positive

- **Encapsulation** — consumers depend on a stable public API, not internal structure.
- **Safe refactoring** — property renames, type changes, and VO migrations don't cascade to consumers.
- **Clean static analysis** — no `@phpstan-ignore` annotations or baseline exclusions needed.
- **Immutability** — enforced by private properties + no setters + private constructor; no `readonly` keyword needed.
- **Consistency** — all 26 events follow the same pattern, making the codebase predictable.

### Negative

- **More boilerplate** — each property requires a getter method (3 lines per property).
- **Slightly more verbose consumer code** — `$event->getTerminalId()` vs `$event->terminalId`.

### Neutral

- **No runtime cost** — getter calls are trivially inlined by PHP's optimizer.

---

## Applies To

All event classes across all three bounded contexts:

**Terminal context** (`Dranzd\StorebunkPos\Domain\Model\Terminal\Event\`):
- `TerminalActivated`
- `TerminalDecommissioned`
- `TerminalDisabled`
- `TerminalMaintenanceSet`
- `TerminalReassigned`
- `TerminalRecommissioned`
- `TerminalRegistered`
- `TerminalRenamed`

**Shift context** (`Dranzd\StorebunkPos\Domain\Model\Shift\Event\`):
- `CashDropRecorded`
- `ShiftClosed`
- `ShiftForceClosed`
- `ShiftOpened`

**PosSession context** (`Dranzd\StorebunkPos\Domain\Model\PosSession\Event\`):
- `CheckoutInitiated`
- `NewOrderStarted`
- `OrderCancelledViaPOS`
- `OrderCompleted`
- `OrderCreatedOffline`
- `OrderDeactivated`
- `OrderMarkedPendingSync`
- `OrderParked`
- `OrderReactivated`
- `OrderResumed`
- `OrderSyncedOnline`
- `PaymentRequested`
- `SessionEnded`
- `SessionStarted`

All future events in this project **must** follow this pattern.
