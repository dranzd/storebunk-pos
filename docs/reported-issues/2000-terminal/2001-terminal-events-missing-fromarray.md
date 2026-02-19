# 2001 — Terminal events missing `fromArray()` — aggregate reconstitution fails

**Type:** Bug
**Status:** Resolved
**Severity:** Critical
**Reported:** 2026-02-19
**Resolved:** 2026-02-19
**Affects:**
- `src/Domain/Model/Terminal/Event/TerminalRegistered.php`
- `src/Domain/Model/Terminal/Event/TerminalActivated.php`
- `src/Domain/Model/Terminal/Event/TerminalDisabled.php`
- `src/Domain/Model/Terminal/Event/TerminalMaintenanceSet.php`
- `src/Domain/Model/Terminal/Terminal.php` (reconstitution path)

---

## Issue

All four Terminal domain events (`TerminalRegistered`, `TerminalActivated`, `TerminalDisabled`, `TerminalMaintenanceSet`) are missing `fromArray()` overrides. The base `GenericMessage::fromArray()` only restores `messageUuid`, `messageName`, `createdAt`, and `metadata` — it does NOT restore typed domain properties (e.g. `$terminalId`, `$branchId`, `$activatedAt`). As a result, reconstituting a Terminal aggregate from the event store throws `TypeError: Typed property must not be accessed before initialization` on any event after the first. This makes all status transition commands (`DisableTerminal`, `ActivateTerminal`, `SetTerminalMaintenance`) fail at runtime.

---

## Findings

`GenericMessage::fromArray()` at `vendor/dranzd/common-cqrs/src/Domain/Message/GenericMessage.php:67`:

```php
public static function fromArray(array $array): static
{
    $message = $reflectionClass->newInstanceWithoutConstructor();
    $message->messageUuid = $array['message_uuid'];
    $message->messageName = $array['message_name'];
    $message->createdAt   = $array['created_at'];
    $message->setMetadata($array['metadata'] ?? []);
    $message->setPayload($array['payload'] ?? []);  // no-op: no $payload property on event classes
    return $message;
}
```

`setPayload()` is a no-op unless the class has a `$payload` property (line 255: `if (!\property_exists($this, 'payload')) { return; }`). Terminal events store their data in typed private properties (`$terminalId`, `$branchId`, etc.), not in a `$payload` array. After `fromArray()` returns, all typed properties remain uninitialized.

Confirmed per event class:

| Event | Uninitialized after `fromArray()` |
|---|---|
| `TerminalRegistered` | `$terminalId`, `$branchId`, `$name`, `$registeredAt` |
| `TerminalActivated` | `$terminalId`, `$activatedAt` |
| `TerminalDisabled` | `$terminalId`, `$disabledAt` |
| `TerminalMaintenanceSet` | `$terminalId`, `$maintenanceSetAt` |

When `AggregateRootTrait` reconstitutes the `Terminal` aggregate from stored events, it calls `fromArray()` on each event then passes it to the corresponding `applyOn*` method. The `applyOn*` methods immediately access these typed properties (e.g. `$event->terminalId()`), triggering `TypeError`.

The first event (`TerminalRegistered`) is always present when a terminal is first created, but any subsequent load from the event store will replay all events — meaning the bug surfaces on the very first status transition after a process restart or repository reload.

---

## Root Cause

The Terminal events were implemented with typed private properties and a `toArray()` method that serializes them, but the corresponding `fromArray()` deserialization override was never added. The base class `fromArray()` has no mechanism to restore typed properties — it can only restore the envelope fields it owns. Each event class is responsible for overriding `fromArray()` to restore its own domain state from `$array['payload']`.

---

## Recommended Action

Add `fromArray(array $array): static` to each of the four Terminal event classes. Each override must:

1. Call `parent::fromArray($array)` to restore the envelope (`messageUuid`, `messageName`, `createdAt`, `metadata`)
2. Read from `$array['payload']` (which matches the keys produced by `toArray()`) and restore all typed properties

Example for `TerminalActivated`:

```php
final public static function fromArray(array $array): static
{
    $event = parent::fromArray($array);
    $event->terminalId = TerminalId::fromNative($array['payload']['terminal_id']);
    $event->activatedAt = new DateTimeImmutable($array['payload']['activated_at']);
    return $event;
}
```

Apply the same pattern to all four events. Verify that `TerminalId::fromNative()` and `BranchId::fromNative()` exist on the value objects (they follow the `dranzd/common-valueobject` convention and should be present).

Also verify whether `Shift` and `PosSession` events have the same gap — if they were implemented after this pattern was established they may also be missing `fromArray()` overrides.

Files to change:
- `src/Domain/Model/Terminal/Event/TerminalRegistered.php`
- `src/Domain/Model/Terminal/Event/TerminalActivated.php`
- `src/Domain/Model/Terminal/Event/TerminalDisabled.php`
- `src/Domain/Model/Terminal/Event/TerminalMaintenanceSet.php`

---

## Owner Response

> _(Owner fills in this section before implementation begins)_

**Decision:**  Proceed with recommended actiion
**Preferred Option:**
**Notes:**

---

## Resolution

**Resolved:** 2026-02-19
**Commit/PR:** fix/2001-terminal-events-fromarray
**Summary:** Added `fromArray(array $array): static` overrides to all 22 domain event classes across Terminal (4), Shift (4), and PosSession (14) aggregates. Each override calls `parent::fromArray($array)` to restore the envelope then reads from `$array['payload']` to restore all typed private properties. All 112 tests pass.
