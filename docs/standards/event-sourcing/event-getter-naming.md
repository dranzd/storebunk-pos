<!-- hash: 30557e54fa248d68760757ecd06088a355c30e6cda8f66bace2f1a2259c1e2c2 -->
# event-getter-naming

Category: event-sourcing
Status: stable
Source: storebunk-pos

---

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

---

## Source File
docs/adr/001-event-getter-prefix.md
