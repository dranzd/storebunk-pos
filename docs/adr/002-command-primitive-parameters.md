# ADR-002: Command Primitive Parameters and Domain-Language Factory Methods

**Status:** Accepted  
**Date:** 2026-03-03  
**Context:** Application Layer Commands

## Decision

All application commands must:

1. **Accept primitive values only** - Commands are consumer-facing and must not expose internal value objects
2. **Use domain-language factory methods** - Commands instantiate via expressive static factory methods instead of `new`
3. **Remain immutable** - Commands are immutable value objects

## Rationale

### Consumer Independence
Commands are the public API boundary between consumers and the application. Requiring consumers to construct value objects:
- Couples consumers to internal implementation details
- Forces consumers to understand domain value object construction
- Makes the API harder to use and more brittle to change

### Domain Language
Factory methods provide expressive, intention-revealing APIs:
- `CancelOrder::because($sessionId, $reason)` - clear intent
- `OpenShift::forCashier(...)` - domain terminology
- `RequestPayment::via($sessionId, $amount, $currency, $method)` - natural language

### Consistency with Events
This aligns commands with the event pattern already established in ADR-001, where events use getter prefixes and factory methods.

## Implementation Pattern

```php
final class CancelOrder extends AbstractCommand
{
    // Private constructor - prevents direct instantiation
    private function __construct(
        private readonly string $sessionId,
        private readonly string $reason
    ) {
        parent::__construct(
            $this->sessionId,
            self::expectedMessageName(),
            [
                'session_id' => $this->sessionId,
                'reason' => $this->reason,
            ]
        );
    }

    // Domain-language factory method
    final public static function because(string $sessionId, string $reason): self
    {
    return new self($sessionId, $reason);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.cancel_order';
    }

    // Getters return value objects (internal use only)
    final public function sessionId(): SessionId
    {
        return SessionId::fromString($this->sessionId);
    }

    final public function reason(): string
    {
        return $this->reason;
    }
}
```

## Usage Examples

**Terminal Commands:**
- `RegisterTerminal::register($terminalId, $branchId, $name)`
- `ActivateTerminal::withId($terminalId)`
- `DisableTerminal::withId($terminalId)`
- `DecommissionTerminal::because($terminalId, $reason)`
- `ReassignTerminal::toBranch($terminalId, $newBranchId)`

**Shift Commands:**
- `OpenShift::forCashier($shiftId, $terminalId, $branchId, $cashierId, $amount, $currency)`
- `CloseShift::withCashAmount($shiftId, $amount, $currency)`
- `RecordCashDrop::ofAmount($shiftId, $amount, $currency)`
- `ForceCloseShift::bySupervisor($shiftId, $supervisorId, $reason)`

**PosSession Commands:**
- `StartSession::onTerminal($sessionId, $shiftId, $terminalId)`
- `StartNewOrder::withOrder($sessionId, $orderId)`
- `CancelOrder::because($sessionId, $reason)`
- `RequestPayment::via($sessionId, $amount, $currency, $paymentMethod)`
- `InitiateCheckout::forSession($sessionId)`
- `CompleteOrder::forSession($sessionId)`
- `ParkOrder::forSession($sessionId)`
- `ResumeOrder::withOrder($sessionId, $orderId)`
- `DeactivateOrder::because($sessionId, $reason)`
- `ReactivateOrder::withOrder($sessionId, $orderId)`
- `EndSession::withId($sessionId)`
- `SyncOrderOnline::forOrder($sessionId, $orderId, $branchId, $customerId)`
- `StartNewOrderOffline::withOrder($sessionId, $orderId)`

## Consequences

### Positive
- Clear API boundary - consumers work with primitives
- Expressive, self-documenting code
- Easier to test - no value object construction in tests
- Flexible - internal value objects can change without breaking consumers
- Consistent with event pattern

### Negative
- Value object construction happens in getter methods (lazy)
- Slightly more verbose command classes
- Factory method naming requires thought

## Migration Notes

All existing command instantiations must be updated from:
```php
new CancelOrder(SessionId::fromString($id), $reason)
```

To:
```php
CancelOrder::because($id, $reason)
```

This affects:
- Command handlers
- Integration tests
- Demo/CLI code
