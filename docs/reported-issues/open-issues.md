# Open Issues Checklist

All unresolved issues. Ordered by severity — most critical first.

When an issue is resolved, remove its line from this file and mark the issue file **Resolved**.

---

#### 🔴 Critical

_(none)_

#### 🟠 High

_(none)_

#### 🟡 Medium

_(none)_

#### 🔵 Low

- [ ] **[9001](9000-offline-sync/9001-order-created-offline-accessor-naming.md)** — `OrderCreatedOffline`, `OrderMarkedPendingSync`, `OrderSyncedOnline` events use `get`-prefixed accessors (`getSessionId()`, `getOrderId()`), inconsistent with the no-prefix convention used by all other events
