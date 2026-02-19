# Open Issues Checklist

All unresolved issues. Ordered by severity — most critical first.

When an issue is resolved, remove its line from this file and mark the issue file **Resolved**.

---

#### 🔴 Critical

- [ ] **[2001](2000-terminal/2001-terminal-events-missing-fromarray.md)** — Terminal events missing `fromArray()` — aggregate reconstitution fails; all status transition commands throw `TypeError` at runtime

#### 🟠 High

- [ ] **[3001](3000-shift/3001-shift-close-no-session-guard.md)** — `CloseShift` dispatches unconditionally — no active session guard; shift can close with unresolved orders
- [ ] **[6003](6000-bc-integration/6003-draft-order-missing-context.md)** — `createDraftOrder()` accepts no customer or branch context; adapter must fabricate `customerId`

#### 🟡 Medium

- [ ] **[6002](6000-bc-integration/6002-deduct-inventory-mapping.md)** — `InventoryServiceInterface::deductInventory()` name is misleading; actual inventory BC operation is `fulfillReservation()` — adapter mapping is undocumented

#### 🔵 Low

- [ ] **[9001](9000-offline-sync/9001-order-created-offline-accessor-naming.md)** — `OrderCreatedOffline`, `OrderMarkedPendingSync`, `OrderSyncedOnline` events use `get`-prefixed accessors (`getSessionId()`, `getOrderId()`), inconsistent with the no-prefix convention used by all other events
