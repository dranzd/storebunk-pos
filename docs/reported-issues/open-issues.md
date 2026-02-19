# Open Issues Checklist

All unresolved issues. Ordered by severity — most critical first.

When an issue is resolved, remove its line from this file and mark the issue file **Resolved**.

---

#### 🔴 Critical

- [ ] **[8001](8000-concurrency/8001-multi-terminal-enforcement-in-memory.md)** — `MultiTerminalEnforcementService` uses in-memory PHP arrays; invariants (one cashier/one terminal per shift) are completely unenforced across HTTP requests or processes

#### 🟠 High

- [ ] **[9002](9000-offline-sync/9002-deactivate-order-command-missing.md)** — `DeactivateOrder` CQRS command and handler are missing; `DraftLifecycleService::checkAndDeactivateInactiveOrders()` is an empty stub — 15-min soft-deactivation cannot be triggered

#### 🟡 Medium

- [ ] **[6001](6000-bc-integration/6001-convert-soft-reservation-to-hard.md)** — `InventoryServiceInterface::convertSoftReservationToHard()` has no mapping in the inventory BC; adapter is a permanent no-op until interface is redesigned
- [ ] **[6002](6000-bc-integration/6002-deduct-inventory-mapping.md)** — `InventoryServiceInterface::deductInventory()` name is misleading; actual inventory BC operation is `fulfillReservation()` — adapter mapping is undocumented

#### 🔵 Low

- [ ] **[9001](9000-offline-sync/9001-order-created-offline-accessor-naming.md)** — `OrderCreatedOffline`, `OrderMarkedPendingSync`, `OrderSyncedOnline` events use `get`-prefixed accessors (`getSessionId()`, `getOrderId()`), inconsistent with the no-prefix convention used by all other events
