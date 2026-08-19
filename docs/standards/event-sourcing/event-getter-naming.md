<!-- hash: 30557e54fa248d68760757ecd06088a355c30e6cda8f66bace2f1a2259c1e2c2 -->
# event-getter-naming

Category: event-sourcing
Status: stable
Source: storebunk-pos

---

All domain events must use private properties with get-prefixed public getter methods. Boolean accessors use is prefix. Properties are not readonly to avoid PHPStan conflicts with static factory methods and reconstitution.

All domain events in this project use the following encapsulation pattern:

### 1. Private Properties (Not Readonly)

Properties are declared `private` with explicit types. They are **not** `readonly` — this avoids PHPStan conflicts with assignment in `occur()` and `setPayload()`, while still preventing external mutation since there are no setters.

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

### 4. `getPayload()` / `setPayload()` for Serialization

These two methods are the whole serialization contract with the event store,
and they operate on the private properties directly from inside the class.
The base class owns `toArray()`/`fromArray()` — envelope fields (message name,
uuid, timestamp) belong to it, and an event that overrode them would have to
re-serialize those correctly every time. Each event describes only its own
payload; nothing else.

`setPayload()` returns early on an empty payload so an envelope carrying no
payload still reconstitutes.

```php
final public function getPayload(): array
{
    return [
        'terminal_id' => $this->terminalId->toNative(),
        'name' => $this->name,
        'registered_at' => $this->registeredAt->format(DATE_ATOM),
    ];
}

/**
 * @param array<string, mixed> $payload
 */
final protected function setPayload(array $payload): void
{
    if (empty($payload)) {
        return;
    }
    $this->terminalId = TerminalId::fromNative($payload['terminal_id']);
    $this->name = $payload['name'];
    $this->registeredAt = new DateTimeImmutable($payload['registered_at']);
}
```
---

---

## Source File
docs/adr/001-event-getter-prefix.md
