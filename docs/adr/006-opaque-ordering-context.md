# ADR-006: Outbound Ordering Context Is Opaque to POS

## @standard: opaque-ordering-context
@category: architecture
@status: stable

Context passed to the Ordering BC at draft-order creation is an opaque, consumer-owned key-value bag. POS forwards it verbatim and must never read, type, or name its keys. Be strict about not introducing external domain language into POS — that is domain leakage.

**Status:** Accepted
**Date:** 2026-08-15
**Context:** `OrderingServiceInterface::createDraftOrder()` / `DraftOrderContext` / `SyncOrderOnline`
**Revises:** the mechanism chosen in reported issue [6003](../reported-issues/6000-bc-integration/6003-draft-order-missing-context.md)

## Decision

1. **`DraftOrderContext` is an opaque context bag.** It holds
   `array<string, mixed>` values with generic `get`/`has`/`with`/`toArray`
   capability and no named fields. The port keeps type-hinting it.
2. **POS never interprets the contents.** `SyncOrderOnline` carries
   `array $context = []`; `SyncOrderOnlineHandler` wraps it and forwards —
   no key is read anywhere in this library. The keys and values are the
   vocabulary of the CONSUMER and the Ordering BC.
3. **Consumers may extend `DraftOrderContext`** (it is deliberately not
   `final`; storage is `protected`) to layer typed accessors over the values.
   The port contract remains the base type, so such extensions cost POS
   nothing.
4. **Adding context never touches POS again.** If the Ordering BC needs a new
   field tomorrow, the consumer adds a key to the array. No POS release, no
   signature change.

## Rationale

Issue 6003 correctly identified that the Ordering BC needs context (branch,
customer, ...) at draft-creation time, but the chosen mechanism gave
`DraftOrderContext` **named fields** (`branchId`, `customerId`). That put
external-domain language inside POS: verification (2026-08-15) showed POS
never read either field anywhere — no PosSession event, invariant, or
projection touches them — every caller passed placeholders, and each future
context field would have forced a POS change. A domain should not have to
adapt whenever a *neighboring* domain's needs grow.

## Caution — domain leakage (binding guidance)

When integrating with external BCs, be strict about which language enters
this library:

- If POS only **transports** a value, it must be opaque (this pattern).
- A concept earns a named type in POS only when POS logic *reads* it to
  enforce an invariant, route a decision, or record state.
- Review lens: any new named field/type referencing another BC's vocabulary
  (customer, sales channel, price list, ...) is a defect unless POS logic
  demonstrably consumes it.

## Consequences

- Positive: POS stays closed against neighboring-domain growth; consumers get
  a typed extension point; port signature stable.
- Negative: no compile-time schema for context content — the contract between
  consumer and Ordering BC lives outside POS (which is exactly where it
  belongs).
