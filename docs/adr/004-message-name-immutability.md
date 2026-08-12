# ADR-004: Message and Event Names Are Immutable

## @standard: message-name-immutability
@category: architecture
@status: stable

The name strings held by commands and events (`expectedMessageName()`) are frozen once released. They are never renamed — not for consistency, style, or alignment with other libraries — unless an actual blocker forces it. Class names may change; the name string a class holds may not.

**Status:** Accepted
**Date:** 2026-08-12
**Context:** All messages — domain events and application commands

## Decision

1. **Never change a message/event name string.** POS names
   (`storebunk.pos.terminal.register`, `storebunk.pos.session.park_order`, …)
   differ in scheme from storebunk-inventory's
   (`dranzd.storebunk.inventory.command.item.register-item`). This deviation is
   **accepted permanently**: persisted event streams, routing, and subscriptions
   reference these strings, and renaming them breaks replay and integration.
2. **Class renames are allowed; the held name is not.** A command or event
   *class* may be renamed or moved freely — the serialized record stores the
   name string, not the class. But changing the name string an event *holds*
   is a new contract and requires **incrementing the event's version**, with
   upcasting/compatibility handling for the old name. That cost is only paid
   for a blocker, never for cosmetics.
3. **New messages follow the existing local scheme** of their library so each
   library stays internally consistent
   (POS: `storebunk.pos.<aggregate>.<snake_case_action>`).

## Rationale

Event-sourced systems persist messages forever. A name string is the one part
of a message that outlives every refactor: it is written into the event store,
matched by projections, and subscribed to by other bounded contexts. A rename
is therefore never cosmetic — it is a data migration plus a coordinated
consumer change. The alignment value of a prettier name is always smaller than
that cost.

## Consequences

- POS message names permanently deviate from the inventory naming scheme.
  This is documented, intentional, and exempt from standardization passes —
  reviewers and AI sessions must not file it as an inconsistency to fix.
- Any proposal that requires a name change must arrive as a reported issue
  marked as a blocker, with the version-increment and upcasting plan included.
