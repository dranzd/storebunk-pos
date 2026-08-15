# ADR-006: Outbound Ordering Context Is Opaque to POS

## @standard: opaque-ordering-context
@category: architecture
@status: stable

Context passed to the Ordering BC at draft-order creation is an opaque, consumer-owned array. POS transports it verbatim from the SyncOrderOnline command to the ordering port and must never read, type, validate, default, or name its keys. Extensibility lives in the payload — never in inheritance. Be strict about not introducing external domain language into POS: that is domain leakage.

**Status:** Accepted
**Date:** 2026-08-15
**Context:** `SyncOrderOnline` / `SyncOrderOnlineHandler` / `OrderingServiceInterface::createDraftOrder()`
**Revises:** the mechanism chosen in reported issue [6003](../reported-issues/6000-bc-integration/6003-draft-order-missing-context.md)

## The Architectural Principle

> POS owns the workflow.
> The consumer owns the meaning of the context.
> POS only transports it across the boundary.

## Decision

1. **`SyncOrderOnline::$context` is an opaque consumer-defined payload.** The
   POS module transports the context to `OrderingServiceInterface` but does
   not interpret its contents. Context keys and semantics are owned by the
   consuming application.
2. **The port receives the raw array.**
   `createDraftOrder(OrderId $orderId, array $context): void` — no wrapper
   type, no POS class for consumers to import in their adapter.
3. **The POS library does not guarantee or define context keys** such as
   `branch_id`, `customer_id`, `business_id`, or similar host-specific
   concepts. A different consumer may legally send
   `['store_id' => …, 'warehouse_id' => …, 'currency' => …]` with zero POS
   changes.
4. **No inheritance-based extensibility.** The earlier idea that consumers
   subclass a POS-provided context class is rejected: the handler necessarily
   instantiates what it knows, so a subclass can never flow through — and
   consumer typing is not POS's problem. The extensibility mechanism is the
   payload itself.

## Ownership Rule

| POS owns | POS does NOT own |
|---|---|
| `sessionId`, `orderId` | `branch_id`, `business_id`, `customer_id` |
| offline/online sync lifecycle | currency, tenant metadata |
| idempotency, pending-sync queue | consumer-specific order-creation requirements |
| `OrderSyncedOnline` behavior | the context schema |
| transporting the opaque context | the context's meaning |

## Handler Discipline (binding)

`SyncOrderOnlineHandler` performs **zero interpretation** of
`$command->context`. Forbidden, reviewable as defects:

- reading a key (`$command->context['branch_id']`)
- validating presence of keys
- injecting defaults or renaming keys
- constructing consumer-specific value objects from the context

POS validates only the outer transport type (it is an `array` — the
typehint). Which keys are required, their formats, and the failure policy
when context is missing are consumer decisions; for tenancy-critical data,
consumers should fail loudly rather than default silently.

## Recommended Consumer-Side Translator Pattern

Consumers should centralize serialization/deserialization of their context
schema in a **consumer-owned** translator or value object rather than
scattering array key literals through their application:

```php
// CONSUMER-OWNED — never shipped by, nor referenced from, the POS library.
final readonly class PosDraftOrderContext
{
    private const BRANCH_ID   = 'branch_id';
    private const CUSTOMER_ID = 'customer_id';

    private function __construct(
        public string $branchId,
        public ?string $customerId,
    ) {
    }

    public static function toArray(string $branchId, ?string $customerId = null): array
    {
        return array_filter(
            [self::BRANCH_ID => $branchId, self::CUSTOMER_ID => $customerId],
            static fn (mixed $value): bool => $value !== null
        );
    }

    public static function fromArray(array $context): self
    {
        // Consumer performs its own validation here.
        return new self(
            branchId: $context[self::BRANCH_ID],
            customerId: $context[self::CUSTOMER_ID] ?? null,
        );
    }
}
```

Producer side:

```php
$command = new SyncOrderOnline(
    $sessionId,
    $orderId,
    PosDraftOrderContext::toArray(branchId: $branchId, customerId: $customerId)
);
```

Adapter side:

```php
final class OrderingServiceAdapter implements OrderingServiceInterface
{
    public function createDraftOrder(OrderId $orderId, array $context): void
    {
        $context = PosDraftOrderContext::fromArray($context);
        // Strongly typed consumer behavior from this point onward.
    }
}
```

The module boundary carries only the opaque array; the translator lives on
both consumer edges.

## Serializability Guidance

The context should contain transport-safe values only: scalars and nested
arrays (e.g. uuid strings, labels). Do not place value objects, entities,
services, or closures in it. (Note: ADR-003 commands carry an empty message
payload and this library has no command rehydration path, so this is a
forward-compatibility guideline, not an enforced serialization contract.)

## Event and Workflow Invariants

- The opaque context is **not** added to `OrderSyncedOnline` — it exists for
  the outbound integration and is not POS domain history. Change the event
  only for an independent POS-domain reason.
- Idempotency semantics are unchanged: check registry → load → sync → store →
  create draft via port → dequeue → mark processed.

## Non-Goals

No `DraftOrderContext` wrapper or factory interfaces, no consumer-context
interfaces, no branch/customer abstractions inside POS, no consumer-specific
validation, no StoreBunk-specific translator classes in this library.

## Caution — domain leakage (binding review guidance)

When integrating with external BCs: if POS only **transports** a value, it
must be opaque. A concept earns a named type in POS only when POS logic
*reads* it to enforce an invariant, route a decision, or record state. Any
new named field or type referencing another BC's vocabulary is a defect
unless POS logic demonstrably consumes it.
