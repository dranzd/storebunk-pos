<!-- hash: f6951ab964f94677db5a2175d7ba40c7d33ef2a8e3c479eb5c51c074faf63d10 -->
# event-driven-state

Category: event-sourcing
Status: stable
Source: storebunk-pos

---

State changes must be captured as a sequence of immutable domain events. The aggregate state is reconstructed by replaying events. Events are the source of truth, not current state.

Instead of storing current state, we store a sequence of events:

```php
// Events are the source of truth
ShiftOpened -> CashDropRecorded -> CheckoutInitiated -> ShiftClosed
```

The aggregate state is reconstructed by replaying events.

### CQRS

---

## Source File
docs/architecture.md
