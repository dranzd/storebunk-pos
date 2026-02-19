# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-02-20

### Added

- **ShiftClosePolicy** domain service — enforces invariant that shift cannot close when active POS sessions exist (#3001)
- **CloseShiftHandler** now injects `ShiftClosePolicy` and `PosSessionReadModelInterface` to guard against closing shifts with active sessions (#3001)
- **DraftOrderContext** DTO — carries `branchId` and optional `customerId` for draft order creation (#6003)
- **DeactivateOrder** command and handler — deactivates the active order due to TTL expiry (#9002)
- **PosSessionReadModelInterface** with `findActiveByShiftId()` method for shift-close guard queries
- **InMemoryPosSessionReadModel** — full projection implementation for POS session lifecycle events
- `fromArray()` reconstitution method on all 18 domain event classes (#2001)
- Reported issues tracking system (`docs/reported-issues/`)
- Library feedback tracking system (`docs/library-feedback/`)
- Issue resolution workflow (`.windsurf/workflows/branch-protection.md`)
- **CloseShiftHandlerTest** — 5 test cases for shift-close guard behavior
- **DeactivateOrderHandlerTest** — 3 test cases for order deactivation
- **ShiftClosePolicyTest** — 4 test cases for policy enforcement

### Changed

- **`OrderingServiceInterface::createDraftOrder()`** now accepts `DraftOrderContext` instead of bare parameters (#6003)
- **`InventoryServiceInterface::confirmReservation()`** renamed from `convertSoftReservationToHard()` with no-op adapter documentation (#6001)
- **`InventoryServiceInterface::fulfillOrderReservation()`** renamed from `deductInventory()` with PHPDoc explaining adapter contract (#6002)
- **MultiTerminalEnforcementService** refactored to stateless invariant checker with read-model-sourced state (#8001)
- Offline event accessors (`OrderCreatedOffline`, `OrderMarkedPendingSync`, `OrderSyncedOnline`) renamed from `get`-prefix to no-prefix convention (#9001)
- Offline command accessors (`StartNewOrderOffline`, `SyncOrderOnline`) renamed from `get`-prefix to no-prefix convention (#9001)
- Added missing `final` keyword to public methods on `StartNewOrderOffline`, `SyncOrderOnlineHandler`, `StartNewOrderOfflineHandler`

### Fixed

- All handler call sites updated for renamed service interface methods
- All aggregate `apply*` methods and read model projections updated for renamed event accessors
- Stale documentation updated: `domain-model.md`, `folder-structure.md`, `features/README.md`, `docs/README.md`

## [1.0.0] - 2026-02-18

### Added

- Initial release — all 37 features complete across 8 phases
- Terminal aggregate with lifecycle management
- Shift aggregate with cash handling and close policies
- PosSession aggregate with Idle/Building/Checkout state machine
- Checkout flow, payment orchestration, and BC integration ports
- Draft lifecycle with TTL enforcement and reservation coordination
- Multi-terminal enforcement and optimistic concurrency
- Offline draft creation and sync queue
