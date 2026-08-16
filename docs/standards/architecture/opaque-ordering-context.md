<!-- hash: 8d6fcf2bda58d4037b455dcbedb52e44d2529ec674374b26ad80dc9db92fed30 -->
# opaque-ordering-context

Category: architecture
Status: stable
Source: storebunk-pos

---

Context passed to the Ordering BC at draft-order creation is an opaque, consumer-owned array. POS transports it verbatim from the SyncOrderOnline command to the ordering port and must never read, type, validate, default, or name its keys. Extensibility lives in the payload — never in inheritance. Be strict about not introducing external domain language into POS: that is domain leakage.

**Status:** Accepted
**Date:** 2026-08-15
**Context:** `SyncOrderOnline` / `SyncOrderOnlineHandler` / `OrderingServiceInterface::createDraftOrder()`
**Revises:** the mechanism chosen in reported issue [6003](../reported-issues/6000-bc-integration/6003-draft-order-missing-context.md)

---

## Source File
docs/adr/006-opaque-ordering-context.md
