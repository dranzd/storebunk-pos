# Fix Strategy: Event Pattern Issues in StoreBunk POS

**Last Updated**: April 6, 2025  
**Target**: 27 POS events  
**Estimated Effort**: 2-3 days for full implementation  
**Priority**: BLOCKER

---

## Executive Summary

This guide provides step-by-step instructions to fix the 4 identified architectural issues in POS events:

1. Implement `getPayload()` / `setPayload()` contract
2. Enable payload-based projection decoupling
3. Set up schema evolution support
4. Standardize on payload-first serialization

**Minimum Fix (to unblock consumer modules)**: Implement `getPayload()` + `setPayload()` on all 27 events.

---

## Part 1: Fix getPayload() in All Events (REQUIRED)

### Step 1: Create Base Event Class with Contract Enforcement

**File**: `src/Domain/Event/BaseAggregateEvent.php`

```php
<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Event;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateEventWithPrivateConstructorTrait;

/**
 * Base class for all POS domain events.
 * 
 * Enforces payload-based serialization contract from common-event-sourcing library.
 * All subclasses MUST implement:
 * - getPayload(): array
 * - setPayload(array): void
 */
abstract class BaseAggregateEvent extends AbstractAggregateEvent
{
    use AggregateEventWithPrivateConstructorTrait;

    /**
     * Must be implemented by all subclasses.
     * Returns event data as associative array.
     * 
     * @return array<string, mixed>
     */
    abstract public function getPayload(): array;

    /**
     * Must be implemented by all subclasses.
     * Hydrates object properties from serialized payload.
     * 
     * @param array<string, mixed> $payload
     */
    abstract protected function setPayload(array $payload): void;
}
```

### Step 2: Refactor Each Event (27 Total)

**Pattern Template**:

```php
<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\{Model}/Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\{Model}\ValueObject\{ValueObject};

/**
 * {EventDescription}
 * 
 * Emitted when {description of when this event occurs}.
 */
final class {EventName} extends BaseAggregateEvent implements DomainEventInterface
{
    // Step 1: Declare all properties
    private {ValueObject} $property1;
    private string $property2;
    private DateTimeImmutable $occurredAt;

    // Step 2: Keep factory method (or create similar pattern)
    final public static function occur(
        {ValueObject} $property1,
        string $property2,
        DateTimeImmutable $occurredAt
    ): self {
        $instance = new self();
        $instance->property1 = $property1;
        $instance->property2 = $property2;
        $instance->occurredAt = $occurredAt;
        return $instance;
    }

    // Step 3: Message name (unchanged)
    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.{module}.{event_name}';
    }

    // Step 4: Implement getPayload() - NEW AND REQUIRED
    /**
     * Returns event payload as serializable array.
     * This is the canonical representation of event data.
     * 
     * @return array<string, mixed>
     */
    final public function getPayload(): array
    {
        return [
            'property_1' => $this->property1->toString(),  // Use toString() for value objects
            'property_2' => $this->property2,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }

    // Step 5: Implement setPayload() - NEW AND REQUIRED
    /**
     * Hydrates object from serialized payload.
     * Called by framework when deserializing events from storage.
     * 
     * @param array<string, mixed> $payload
     */
    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;  // Defensive: empty payload during early construction
        }

        $this->property1 = {ValueObject}::fromNative($payload['property_1']);
        $this->property2 = $payload['property_2'];
        $this->occurredAt = new DateTimeImmutable($payload['occurred_at']);
    }

    // Step 6: Implement occurredAt() 
    final public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    // Step 7: Keep existing getters (for backward compatibility)
    final public function getProperty1(): {ValueObject}
    {
        return $this->property1;
    }

    final public function getProperty2(): string
    {
        return $this->property2;
    }

    // Optional Step 8: Remove toArray() if using old pattern
    // The parent class now handles toArray() via getPayload()
}
```

### Step 3: Update Individual Events

For each of the 27 events, follow this checklist:

**Terminal Events (8)**:
- [ ] TerminalActivated
- [ ] TerminalDecommissioned
- [ ] TerminalDisabled
- [ ] TerminalMaintenanceSet
- [ ] TerminalReassigned
- [ ] TerminalRecommissioned
- [ ] TerminalRegistered
- [ ] TerminalRenamed

**Session Events (19)**:
- [ ] CheckoutInitiated
- [ ] NewOrderStarted
- [ ] OrderCancelledViaPOS
- [ ] OrderCompleted
- [ ] OrderCreatedOffline
- [ ] OrderDeactivated
- [ ] OrderMarkedPendingSync
- [ ] OrderResumed
- [ ] OrderSyncedOnline
- [ ] PaymentRequested
- [ ] SessionEnded
- [ ] SessionStarted
- [ ] (Plus remaining session events)

### Step 4: Real Example - Complete Implementation

**File**: `src/Domain/Model/PosSession/Event/SessionStarted.php`

```php
<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

/**
 * SessionStarted Event
 * 
 * Emitted when a new POS session is initiated by a cashier.
 * Contains terminal, shift, and timing information.
 */
final class SessionStarted extends BaseAggregateEvent implements DomainEventInterface
{
    private SessionId $sessionId;
    private ShiftId $shiftId;
    private TerminalId $terminalId;
    private DateTimeImmutable $startedAt;

    /**
     * Factory method: Create a new session started event
     */
    final public static function occur(
        SessionId $sessionId,
        ShiftId $shiftId,
        TerminalId $terminalId,
        DateTimeImmutable $startedAt
    ): self {
        $instance = new self();
        $instance->sessionId = $sessionId;
        $instance->shiftId = $shiftId;
        $instance->terminalId = $terminalId;
        $instance->startedAt = $startedAt;
        return $instance;
    }

    /**
     * Event message name (unchanged)
     */
    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.started';
    }

    /**
     * Serialize to payload array
     * 
     * This is the PRIMARY serialization method.
     * All event data is represented here.
     * 
     * @return array<string, mixed>
     */
    final public function getPayload(): array
    {
        return [
            'session_id' => $this->sessionId->toString(),
            'shift_id' => $this->shiftId->toString(),
            'terminal_id' => $this->terminalId->toString(),
            'started_at' => $this->startedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Deserialize from payload array
     * 
     * This is called by the framework when loading events.
     * Safely hydrates all properties from saved payload.
     * 
     * @param array<string, mixed> $payload
     */
    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;  // During construction before fromArray()
        }

        $this->sessionId = SessionId::fromNative($payload['session_id']);
        $this->shiftId = ShiftId::fromNative($payload['shift_id']);
        $this->terminalId = TerminalId::fromNative($payload['terminal_id']);
        $this->startedAt = new DateTimeImmutable($payload['started_at']);
    }

    /**
     * When the session started (timestamp)
     */
    final public function occurredAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    /**
     * Accessors (preserved for backward compatibility)
     */
    final public function getSessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function getShiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }
}
```

---

## Part 2: Write Tests (Required for All Events)

### Test Template

**File**: `tests/Domain/Model/PosSession/Event/SessionStartedTest.php`

```php
<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Domain\Model\PosSession\Event;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class SessionStartedTest extends TestCase
{
    private SessionId $sessionId;
    private ShiftId $shiftId;
    private TerminalId $terminalId;
    private DateTimeImmutable $startedAt;

    protected function setUp(): void
    {
        $this->sessionId = SessionId::fromNative('session-123');
        $this->shiftId = ShiftId::fromNative('shift-456');
        $this->terminalId = TerminalId::fromNative('terminal-789');
        $this->startedAt = new DateTimeImmutable('2025-04-06T10:00:00Z');
    }

    /**
     * Test that getPayload() returns all event data
     * (This would FAIL with current code - returns [])
     */
    public function testGetPayloadReturnsAllEventData(): void
    {
        $event = SessionStarted::occur(
            $this->sessionId,
            $this->shiftId,
            $this->terminalId,
            $this->startedAt
        );

        $payload = $event->getPayload();

        // CRITICAL: Payload must not be empty
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload, 'getPayload() must return event data, not empty array');

        // Verify all fields are present
        $this->assertArrayHasKey('session_id', $payload);
        $this->assertArrayHasKey('shift_id', $payload);
        $this->assertArrayHasKey('terminal_id', $payload);
        $this->assertArrayHasKey('started_at', $payload);

        // Verify values are correct
        $this->assertEquals('session-123', $payload['session_id']);
        $this->assertEquals('shift-456', $payload['shift_id']);
        $this->assertEquals('terminal-789', $payload['terminal_id']);
        $this->assertEquals('2025-04-06T10:00:00+00:00', $payload['started_at']);
    }

    /**
     * Test that setPayload() correctly hydrates event
     */
    public function testSetPayloadHydratesEventCorrectly(): void
    {
        $payload = [
            'session_id' => 'session-123',
            'shift_id' => 'shift-456',
            'terminal_id' => 'terminal-789',
            'started_at' => '2025-04-06T10:00:00+00:00',
        ];

        $event = SessionStarted::fromArray([
            'message_uuid' => 'msg-123',
            'message_name' => 'storebunk.pos.session.started',
            'created_at' => '2025-04-06T10:00:00.000000+00:00',
            'metadata' => [],
            'payload' => $payload,
        ]);

        // After deserialization, getters should work
        $this->assertEquals('session-123', $event->getSessionId()->toString());
        $this->assertEquals('shift-456', $event->getShiftId()->toString());
        $this->assertEquals('terminal-789', $event->getTerminalId()->toString());
    }

    /**
     * Test round-trip serialization: create → serialize → deserialize → verify
     */
    public function testSerializationRoundTrip(): void
    {
        // Create event
        $original = SessionStarted::occur(
            $this->sessionId,
            $this->shiftId,
            $this->terminalId,
            $this->startedAt
        );

        // Serialize
        $array = $original->toArray();

        // Deserialize
        $hydrated = SessionStarted::fromArray($array);

        // Verify payload is preserved
        $this->assertEquals(
            $original->getPayload(),
            $hydrated->getPayload(),
            'Payload must be identical after round-trip'
        );

        // Verify all getters still work
        $this->assertEquals(
            $original->getSessionId()->toString(),
            $hydrated->getSessionId()->toString()
        );
        $this->assertEquals(
            $original->getShiftId()->toString(),
            $hydrated->getShiftId()->toString()
        );
    }

    /**
     * Test that event store serialization includes payload
     */
    public function testEventStoreSerializationIncludesPayload(): void
    {
        $event = SessionStarted::occur(
            $this->sessionId,
            $this->shiftId,
            $this->terminalId,
            $this->startedAt
        );

        $array = $event->toArray();

        // This is what gets stored in event store
        $json = json_encode($array);
        $decoded = json_decode($json, true);

        // CRITICAL: Payload must not be empty when persisted
        $this->assertIsArray($decoded['payload']);
        $this->assertNotEmpty(
            $decoded['payload'],
            'Event payload must not be empty when stored in event store'
        );

        // Verify message name is correct
        $this->assertEquals(
            'storebunk.pos.session.started',
            $decoded['message_name']
        );
    }

    /**
     * Test that message name is consistent
     */
    public function testExpectedMessageName(): void
    {
        $this->assertEquals(
            'storebunk.pos.session.started',
            SessionStarted::expectedMessageName()
        );
    }

    /**
     * Test that occurredAt returns correct timestamp
     */
    public function testOccurredAtReturnsCorrectTimestamp(): void
    {
        $event = SessionStarted::occur(
            $this->sessionId,
            $this->shiftId,
            $this->terminalId,
            $this->startedAt
        );

        $this->assertEquals(
            $this->startedAt,
            $event->occurredAt()
        );
    }
}
```

---

## Part 3: Verification Checklist

### Before Deployment

Run these checks to verify fix is complete:

```bash
# 1. Verify all tests pass
./vendor/bin/phpunit tests/Domain/Model/PosSession/Event/ -v
./vendor/bin/phpunit tests/Domain/Model/Terminal/Event/ -v

# 2. Verify no empty payloads
php tests/verify-payloads.php

# 3. Static analysis
./vendor/bin/phpstan analyse src/Domain/Model/*/Event/

# 4. Verify backward compatibility
git diff src/Domain/Model/*/Event/ | grep -c "getPayload\|setPayload"
# Should show all 27 events have been updated

# 5. Test serialization pipeline
php tests/verify-serialization-pipeline.php
```

### Verification Script

**File**: `tests/verify-payloads.php`

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use DateTimeImmutable;

$failures = [];

// Test SessionStarted
try {
    $event = SessionStarted::occur(
        SessionId::fromNative('test'),
        ShiftId::fromNative('test'),
        TerminalId::fromNative('test'),
        new DateTimeImmutable()
    );
    
    $payload = $event->getPayload();
    if (empty($payload)) {
        $failures[] = "SessionStarted::getPayload() returns empty array";
    }
} catch (Exception $e) {
    $failures[] = "SessionStarted error: " . $e->getMessage();
}

// Test TerminalRegistered
try {
    $event = TerminalRegistered::occur(
        TerminalId::fromNative('test'),
        BranchId::fromNative('test'),
        'Test Terminal',
        new DateTimeImmutable()
    );
    
    $payload = $event->getPayload();
    if (empty($payload)) {
        $failures[] = "TerminalRegistered::getPayload() returns empty array";
    }
} catch (Exception $e) {
    $failures[] = "TerminalRegistered error: " . $e->getMessage();
}

if (empty($failures)) {
    echo "✅ All payload checks passed\n";
    exit(0);
} else {
    echo "❌ Payload verification failed:\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}
```

---

## Part 4: Migration Order (Recommended)

### Week 1: Terminal Events (Day 1)

```
1. Create BaseAggregateEvent
2. Migrate TerminalRegistered
3. Migrate TerminalActivated
4. Migrate TerminalDeactivated
5. Migrate TerminalDisabled
6. Migrate TerminalRenamed
7. Migrate TerminalRecommissioned
8. Migrate TerminalReassigned
9. Migrate TerminalMaintenanceSet
10. Write tests for all terminal events
```

### Week 1: Session Events (Days 2-3)

```
1. Migrate SessionStarted
2. Migrate SessionEnded
3. Migrate NewOrderStarted
4. Migrate OrderCreatedOffline
5. Migrate OrderCancelledViaPOS
6. Migrate OrderSyncedOnline
7. Migrate OrderMarkedPendingSync
8. Migrate OrderDeactivated
9. Migrate OrderResumed
10. Migrate OrderCompleted
11. Migrate CheckoutInitiated
12. Migrate PaymentRequested
13. Migrate remaining session events
14. Write tests for all session events
```

### Week 2: Testing & Verification (Day 1)

```
1. Run full test suite
2. Verify round-trip serialization
3. Run static analysis
4. Check event store compatibility
5. Performance testing
```

### Week 2: Documentation & Deployment (Day 2)

```
1. Update REFACTORING_STATUS.md
2. Document payload field names
3. Create migration guide for consumers
4. Deploy to staging
5. Deploy to production
```

---

## Part 5: Common Pitfalls & Solutions

### Pitfall 1: Value Object Serialization

**Problem**: How to serialize complex value objects?

**Solution**: Use `toString()` method (already implemented in your value objects)

```php
// ✅ Correct
'terminal_id' => $this->terminalId->toString(),

// ❌ Wrong
'terminal_id' => $this->terminalId,  // Might not serialize correctly
'terminal_id' => $this->terminalId->toNative(),  // Could expose internal format
```

### Pitfall 2: Date Serialization Format

**Problem**: Which date format to use?

**Solution**: Always use `\DateTimeInterface::ATOM` (RFC 3339)

```php
// ✅ Correct
'started_at' => $this->startedAt->format(\DateTimeInterface::ATOM),
// Result: "2025-04-06T10:00:00+00:00"

// ❌ Wrong
'started_at' => $this->startedAt->format('Y-m-d H:i:s'),
// Missing timezone info, breaks consistency
```

### Pitfall 3: Null Value Handling

**Problem**: How to handle optional fields?

**Solution**: Use null-coalescing in setPayload

```php
// ✅ Correct
final protected function setPayload(array $payload): void
{
    if (empty($payload)) {
        return;
    }

    $this->optionalField = $payload['optional_field'] ?? null;
}

// Return null in getPayload
final public function getPayload(): array
{
    return [
        'optional_field' => $this->optionalField,
        // null values are included in payload
    ];
}
```

### Pitfall 4: Empty Payload During Construction

**Problem**: `setPayload([])` called before event is fully constructed

**Solution**: Add guard clause at start of setPayload

```php
final protected function setPayload(array $payload): void
{
    if (empty($payload)) {
        return;  // ← Guard clause prevents errors during construction
    }

    // Safe to access properties now
    $this->property = $payload['property'];
}
```

---

## Part 6: Backward Compatibility

### Option A: Keep Both Patterns (During Transition)

```php
// Keep old pattern for compatibility
final public function toArray(): array
{
    return [
        'session_id' => $this->sessionId->toString(),
        'shift_id' => $this->shiftId->toString(),
        'terminal_id' => $this->terminalId->toString(),
        'started_at' => $this->startedAt->format(DATE_ATOM),
    ];
}

final public static function fromArray(array $array): static
{
    $event = parent::fromArray($array);
    $event->sessionId = SessionId::fromNative($array['payload']['session_id']);
    $event->shiftId = ShiftId::fromNative($array['payload']['shift_id']);
    $event->terminalId = TerminalId::fromNative($array['payload']['terminal_id']);
    $event->startedAt = new DateTimeImmutable($array['payload']['started_at']);
    return $event;
}

// New canonical pattern
final public function getPayload(): array
{
    return [
        'session_id' => $this->sessionId->toString(),
        'shift_id' => $this->shiftId->toString(),
        'terminal_id' => $this->terminalId->toString(),
        'started_at' => $this->startedAt->format(\DateTimeInterface::ATOM),
    ];
}

final protected function setPayload(array $payload): void
{
    if (empty($payload)) {
        return;
    }
    $this->sessionId = SessionId::fromNative($payload['session_id']);
    $this->shiftId = ShiftId::fromNative($payload['shift_id']);
    $this->terminalId = TerminalId::fromNative($payload['terminal_id']);
    $this->startedAt = new DateTimeImmutable($payload['started_at']);
}
```

### Option B: Replace Old Pattern (Clean Break)

If no existing consumers depend on old pattern:

```php
// Remove toArray() entirely - parent handles it via getPayload()
// Remove fromArray() custom logic - parent handles it via setPayload()

// Just implement the new contract
final public function getPayload(): array { ... }
final protected function setPayload(array $payload): void { ... }
```

---

## Part 7: Success Metrics

After implementation, verify:

```php
// ✅ Metric 1: All events have working getPayload()
foreach ($events as $event) {
    assert($event->getPayload() !== []);
}

// ✅ Metric 2: Round-trip serialization works
foreach ($events as $event) {
    $restored = get_class($event)::fromArray($event->toArray());
    assert($event->getPayload() === $restored->getPayload());
}

// ✅ Metric 3: Event store payloads are populated
foreach ($persistedEvents as $data) {
    assert(!empty($data['payload']));
}

// ✅ Metric 4: Projections can use payload-based access
$payload = $event->getPayload();
assert($payload['session_id'] === $projectionData['session_id']);
```

---

## Quick Reference: Template Copy-Paste

### Minimal Event Template (Copy & Adapt)

```php
<?php
declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\{Module}/Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;

final class {EventName} extends BaseAggregateEvent implements DomainEventInterface
{
    private \{ValueObject} $prop1;
    private string $prop2;
    private DateTimeImmutable $occurredAt;

    final public static function occur(
        \{ValueObject} $prop1,
        string $prop2,
        DateTimeImmutable $occurredAt
    ): self {
        $instance = new self();
        $instance->prop1 = $prop1;
        $instance->prop2 = $prop2;
        $instance->occurredAt = $occurredAt;
        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.{module}.{event_name}';
    }

    final public function getPayload(): array
    {
        return [
            'prop_1' => $this->prop1->toString(),
            'prop_2' => $this->prop2,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->prop1 = \{ValueObject}::fromNative($payload['prop_1']);
        $this->prop2 = $payload['prop_2'];
        $this->occurredAt = new DateTimeImmutable($payload['occurred_at']);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    final public function getProp1(): \{ValueObject}
    {
        return $this->prop1;
    }

    final public function getProp2(): string
    {
        return $this->prop2;
    }
}
```

---

## Timeline & Estimation

| Phase | Task | Effort | Duration |
|-------|------|--------|----------|
| 1 | Create BaseAggregateEvent | 1 hour | Mon AM |
| 1 | Migrate 8 Terminal events | 2 hours | Mon PM |
| 1 | Migrate 19 Session events | 3 hours | Tue AM |
| 1 | Write tests (27 events) | 3 hours | Tue PM |
| 2 | Run full test suite | 1 hour | Wed AM |
| 2 | Verify event store compat | 2 hours | Wed PM |
| 2 | Static analysis & fixes | 1 hour | Thu AM |
| 3 | Documentation & deploy prep | 2 hours | Thu PM |
| 3 | Deploy & verify production | 2 hours | Fri AM |

**Total: ~17 hours / ~2 days**

---

## Support Resources

- Library docs: `vendor/dranzd/common-event-sourcing/README.md`
- Event Serialization: `vendor/dranzd/common-event-sourcing/docs/guides/event-serialization-contract.md`
- Example (Inventory): `/media/dev/dranzd/storebunk-inventory/src/Domain/Model/Barcode/Event/Registered.php`
- CQRS Docs: `vendor/dranzd/common-cqrs/README.md`

---

## Questions & Answers

**Q: Do I need to migrate all 27 events at once?**  
A: Ideally yes, but can do in batches by module (Terminal first, then Session).

**Q: Will this break existing code?**  
A: No. Old code continues to work. New `getPayload()` method is additive.

**Q: What if I have custom event subclasses?**  
A: Apply same pattern. They must also implement `getPayload()` and `setPayload()`.

**Q: How do I verify the fix works?**  
A: Run test suite + verify-payloads.php script. Check event store has non-empty payloads.

**Q: Can I deploy incrementally?**  
A: Yes. Deploy events one module at a time. Consumers can wait until all are ready.
