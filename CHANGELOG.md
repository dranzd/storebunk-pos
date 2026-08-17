# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

> **Breaking changes pending release.** All command factory methods are gone —
> commands are constructed with `new` (ADR-003) — and `SyncOrderOnline` now
> carries an opaque context array (ADR-006).

### Added

- Demo CLI: `terminal rename|reassign|decommission|recommission` subcommands
  — the four Terminal handlers that existed but were never registered in the
  demo are now wired and documented (issue 1001).
- One-open-shift-per-terminal and one-open-shift-per-cashier invariants are
  now enforced (issue 8002): `OpenShiftHandler` consults
  `MultiTerminalEnforcementService` against a `ShiftReadModelInterface`,
  which gains `openShiftsByTerminal()` / `activeTerminalByCashier()` and a
  first implementation, `InMemoryShiftReadModel` (projected from
  `ShiftOpened`/`ShiftAssigned`/`ShiftUnassigned`/`ShiftClosed`/`ShiftForceClosed`).
  A second `OpenShift` on an occupied terminal, or by a cashier already
  running a shift, is refused with an `InvariantViolationException`.
  `AssignShift` refuses an assignee who operates another open shift, and
  `UnassignShift` refuses when the original opener does (unassigning hands
  the shift back to them, via the new `Shift::openedBy()` accessor).
- Atomic shift-slot reservations (issue 8003): the new
  `ShiftSlotReservationInterface` (`reserveForOpen` / `prepareTransfer` /
  `commitTransfer` / `abortTransfer` / `releaseShift` / `reconcile`) is the
  concurrency authority for those invariants — open claims slots BEFORE
  storing, close/force-close release them after, and the five shift handler
  constructors take the port. Ships with a single-process
  `InMemoryShiftSlotReservation` and a file-lock-backed demo
  implementation; two concurrent demo `shift open` commands now resolve to
  exactly one winner. Moving a shift's cashier runs as prepare → store →
  commit: the outgoing cashier keeps their slot for the whole in-flight
  window, so a failed assign/unassign rolls back to a state that still
  matches the aggregate instead of freeing someone who may already have
  taken another shift. The shift id itself is claimed on open, transfers
  refuse closed shifts and shifts with a transfer already in flight, and a
  cleanup that fails after a command persisted raises
  `SlotCleanupFailedException` (original failure preserved as the cause)
  pointing at `reconcile` — exposed as `./demo shift reconcile`, which
  rebuilds the slots from the committed shifts. The slot algebra shared by
  every implementation lives in one place, `ShiftSlotBook`. Demo
  `state clear` resets events, state, and shift slots as one coordinated,
  recoverable operation. `ShiftReadModelInterface` is query state only (the
  briefly-added enforcement-map methods are gone again).

### Changed

- **BREAKING** — All 27 application commands follow the storebunk-inventory
  standard ([ADR-003](docs/adr/003-command-structure-inventory-alignment.md)):
  public constructor, `public readonly` primitive properties, no static
  factories, no accessor methods; handlers own value-object conversion.
  Migrate `CancelOrder::because($id, $reason)` →
  `new CancelOrder($id, $reason)`, `StartSession::onTerminalForCashier(...)` →
  `new StartSession($sessionId, $shiftId, $terminalId, $cashierId)`, etc.
  Deterministic command ids (offline replay) now use the base-class
  `withMessageUuid()` instead of a constructor parameter. Message name
  strings are unchanged and frozen ([ADR-004](docs/adr/004-message-name-immutability.md)).
- **BREAKING** — `SyncOrderOnline` no longer takes `branchId`/`customerId`;
  it carries an opaque `array $context` forwarded verbatim to
  `OrderingServiceInterface::createDraftOrder(OrderId, array $context)`.
  The `DraftOrderContext` DTO is removed. Context keys belong to the
  consumer ([ADR-006](docs/adr/006-opaque-ordering-context.md)).
- House code style: strings use single quotes unless interpolation or escape
  sequences are needed (enforced via `Squiz.Strings.DoubleQuoteUsage.NotRequired`).

### Added

- ADR-003/004/005/006; `docs/reported-issues` inbox standard
  (`incoming-report.md` + `/triage`).
- Demo: file-backed event store so multi-step scenarios work across CLI
  invocations; `session deactivate` subcommand; scenario fixes.

### Fixed

- Demo `session sync` removed the synced order from `pending_sync_order_ids`
  via a stale read-modify-write; a concurrent push from another process could
  be clobbered. `StateStore::removeFromList()` now filters the current
  on-disk list under the sidecar lock (issue 9003).
- Demo CLI arg parsing: the space-separated option form (`--name SpacedName`)
  silently misparsed (the flag stringified to "1"); it is now rejected loudly
  with the correct `--name=value` spelling, and scenario scripts honor
  `POS_DEMO_DATA_DIR` instead of a hardcoded state-file path (issue 1001).
- Repositories now depend on the `EventStore` interface instead of the
  concrete `InMemoryEventStore`.
- Redelivering a `SyncOrderOnline` with the same deterministic message uuid
  now succeeds instead of tripping the "Order is not in pending sync list"
  invariant (the in-memory idempotency registry is rebuilt from events on
  restart and cannot recover the command id; the aggregate now remembers
  synced orders). The retry re-issues the draft-order call, healing a prior
  failure between the stored sync event and `createDraftOrder()`. Delivery
  to `OrderingServiceInterface::createDraftOrder()` is therefore
  **at-least-once**: consumer implementations must be idempotent per order
  id (documented on the interface).
- `PosSession` now refuses to deactivate or park an order during checkout
  (`InvariantViolationException`) — either would strand the hard inventory
  reservation taken at checkout initiation.
- `SessionState` gains a `Payment` state, entered on the first
  `PaymentRequested`. Once payment has been received the order can only be
  completed (or receive further split payments) — `cancelOrder`,
  `deactivateOrder`, and `parkOrder` all refuse, so the expiry sweep can
  never silently cancel a paid order (inventory released, money kept).
  A paid-but-uncompleted order deliberately stays active for manual
  operational review; post-payment cancellation is a downstream sales-order
  operation, never a POS action. Message names are unchanged.
- `DraftLifecycleService` sweeps (inactivity deactivation and expiry
  cancellation) are best-effort per session: a session that refuses on a
  domain invariant is skipped and the sweep continues; any other failure
  still propagates.
- Demo: shift open no longer crashes without `--cashier-id`; resumed or
  reactivated orders are targeted correctly by the follow-on
  checkout/pay/complete defaults.
- Demo persistence hardening: `FileEventStore` and `StateStore` now merge
  concurrent writes under a sidecar lock, write atomically via temp file +
  rename, fail loudly on unreadable/corrupt/unwritable files instead of
  silently losing or resurrecting data, and `./demo/demo state clear` works
  even when a store is corrupt (handled before bootstrap) as a coordinated
  all-or-nothing reset of both files.

## [2.0.0] - 2026-06-01

> **Breaking release.** Starting a POS session now requires an operating cashier. Consumers calling `StartSession::onTerminal(...)` must migrate to `StartSession::onTerminalForCashier(...)`.

### Added

- **POS session operator identity** (resolves #4002 Part A) — `PosSession` now records its operating cashier. New `StartSession::onTerminalForCashier(sessionId, shiftId, terminalId, cashierId)`; `SessionStarted` carries a `cashier_id`; new module-owned `PosSession\ValueObject\CashierId`; `PosSession::cashierId()` getter. The operator is distinct from the host `User`, which continues to travel as `ActorCapable` actor metadata (`_actor_id`).
- **Shift assignment / membership** (resolves #4002 Part B) — new `ShiftAssigned` and `ShiftUnassigned` events with `AssignShift` / `UnassignShift` commands. A shift can be assigned to a cashier with up to 3 fallback cashiers, and cleared back to "open". New getters `Shift::assignee()`, `Shift::fallbackCashiers()`, `Shift::isAssigned()`. Keyed by the module's `CashierId`.
- **Demo CLI** — `shift assign`, `shift unassign`, and a `--cashier-id` option on `session start` (defaulting to the last shift's cashier).
- **Tests** — `StartSessionHandlerTest`, `AssignShiftHandlerTest`, `UnassignShiftHandlerTest`, payload-contract tests for the two new Shift events, and expanded `ShiftTest` / `PosSessionTest` (208 tests total).

### Changed

- **BREAKING** — `SessionStarted::occur()` and `PosSession::start()` gained a required `CashierId` argument; the `storebunk.pos.session.started` payload gains a non-null `cashier_id` key.
- POS domain event count is now **28** (added `ShiftAssigned`, `ShiftUnassigned`); event-pattern specification and ADR-001 updated accordingly.
- `docs/reported-issues` index synced to authoritative issue-file statuses.

### Removed

- **BREAKING** — `StartSession::onTerminal()` (the operator-less factory). A session always has an operator; the package is pre-deployment, so no compatibility shim is retained.

### Fixed

- Demo bootstrap wired `CloseShiftHandler` with only the repository, but it requires `ShiftClosePolicy` and `PosSessionReadModelInterface` — a pre-existing defect that fatally broke the demo CLI on startup. Now wired correctly.

## [1.1.1] - 2026-04-06

### Added

- **BaseAggregateEvent** abstract base class — provides standard implementation of `getPayload()` and `setPayload()` for all domain events
- **PayloadContractTest** — comprehensive PHPUnit test suite covering all 26 POS domain events with payload serialization and hydration verification
- **AI Documentation Policy** (`docs/ai-documentation-policy.md`) — establishes guidelines for raw-discussion docs (off-limits) and specification storage with kebab-case naming
- **Event Pattern Specification** (`docs/specifications/event-pattern-specification.md`) — consolidated analysis and implementation plan for event payload contract fix

### Changed

- All 26 POS domain events refactored to extend `BaseAggregateEvent` instead of directly implementing event interface
- Makefile refactored to match storebunk-inventory UI style with explicit help targets and section headers
- Removed ad-hoc verification script (`verify_payload_fix.php`) in favor of automated PHPUnit tests
- PHPUnit configuration migrated to current schema (removes deprecation notice)

### Fixed

- Event payload contract: all events now properly implement `getPayload()` and `setPayload()` with complete data serialization (fixes #2001)
- Terminal and Shift events — restored missing event parameters that were previously lost
- Legacy `toArray()`/`fromArray()` patterns replaced with library-standard payload contract
- PHPUnit deprecation: `phpunit.xml` now validates against current schema

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
