# StoreBunk POS — Demo CLI Specification

> **Status:** Implemented — see `demo/` and `demo/README.md` for usage.
> This document defines the design, structure, commands, and scenarios for the POS Demo CLI.
> Implementation follows the same patterns as `storebunk-inventory/demo/`. Where this
> spec and the implementation differ, the implementation (and `demo/README.md`) win.

---

## Purpose

The Demo CLI proves that the `dranzd/storebunk-pos` library works as a **standalone, framework-agnostic PHP library** without any web framework. It exercises the full domain through the CQRS command/query buses using in-memory infrastructure.

It is **not** a production UI. It is a developer tool for:

- Verifying the library works end-to-end
- Demonstrating domain scenarios interactively
- Running scripted lifecycle scenarios for documentation and testing
- Onboarding contributors to the domain model

---

## Design Principles

1. **No framework** — pure PHP CLI, bootstrapped manually
2. **Uses CQRS buses** — all operations go through `SimpleCommandBus` / `SimpleQueryBus`
3. **File-backed event store, in-memory repositories** — `FileEventStore` (demo-only, JSON write-through) feeds `InMemoryTerminalRepository`, `InMemoryShiftRepository`, `InMemoryPosSessionRepository`, `InMemoryTerminalReadModel`
4. **Stub BC services** — `StubOrderingService`, `StubInventoryService`, `StubPaymentService` from `tests/Stub/`
5. **JSON event store persistence** — events persisted to a JSON file (like inventory demo), enabling stateful multi-command sessions
6. **Idempotency support** — `IdempotencyRegistry` wired for offline commands
7. **Colored terminal output** — consistent with inventory demo style

---

## Entry Point

```
demo/demo
```

Usage:

```bash
./demo <service> <command> [options] [arguments]
```

Services: `terminal`, `shift`, `session`

---

## File Structure

```
demo/
├── demo                              # Main entry point (PHP CLI script)
├── bootstrap.php                     # Wires repos, buses, stubs, event store,
│                                     #   and rebuilds offline-sync state from events
├── cli/
│   ├── Output.php                    # Colored terminal output helpers
│   ├── CliArgs.php                   # Argument/option parser
│   ├── StateStore.php                # JSON state file (last_* ids, id lists)
│   ├── FileEventStore.php            # JSON-backed event store for demo persistence
│   ├── FileShiftSlotReservation.php  # Cross-process shift-slot claims (lock + atomic rename)
│   ├── DemoReset.php                 # Coordinated all-or-nothing reset of the three stores
│   ├── Utils.php                     # Formatting helpers
│   └── services/
│       ├── terminal.php              # Terminal service CLI handler
│       ├── shift.php                 # Shift service CLI handler
│       └── session.php               # Session service CLI handler
├── scenarios/
│   ├── 01-full-shift-lifecycle.sh    # Complete shift open → orders → close
│   ├── 02-checkout-flow.sh           # Draft → checkout → payment → complete
│   ├── 03-park-and-resume.sh         # Park order, start new, resume parked
│   ├── 04-draft-ttl-expiry.sh        # Deactivate order, reactivate with re-reservation
│   ├── 05-force-close-shift.sh       # Supervisor force-close scenario
│   ├── 06-offline-sync.sh            # Offline order creation and sync
│   └── 07-concurrency-conflict.sh    # Optimistic locking conflict demonstration
└── data/
    ├── demo-state.json               # ID state (git-ignored at runtime)
    ├── events.json                   # Persisted events (git-ignored)
    └── shift-slots.json              # Shift-slot claims (git-ignored)
```

---

## Bootstrap (`bootstrap.php`)

Wires the full application stack:

```
FileEventStore (demo/data/events.json)
    → InMemoryTerminalRepository
    → InMemoryShiftRepository
    → InMemoryPosSessionRepository
    → InMemoryTerminalReadModel (projected per invocation)
    → InMemoryPosSessionReadModel (projected during replay)
    → InMemoryShiftReadModel (projected during replay; seeds/reconciles slots)

StubOrderingService
StubInventoryService
StubPaymentService

IdempotencyRegistry ─┐ rebuilt from persisted session events
PendingSyncQueue   ──┘ on every bootstrap
ShiftClosePolicy
ShiftSlotBook ──→ FileShiftSlotReservation (demo/data/shift-slots.json)
                  the concurrency authority every shift handler goes through

CommandRegistry (InMemoryHandlerRegistry)
    → RegisterTerminalHandler
    → ActivateTerminalHandler
    → DisableTerminalHandler
    → SetTerminalMaintenanceHandler
    → RenameTerminalHandler
    → ReassignTerminalHandler
    → DecommissionTerminalHandler
    → RecommissionTerminalHandler
    → OpenShiftHandler
    → AssignShiftHandler
    → UnassignShiftHandler
    → CloseShiftHandler
    → ForceCloseShiftHandler
    → RecordCashDropHandler
    → StartSessionHandler
    → StartNewOrderHandler
    → ParkOrderHandler
    → ResumeOrderHandler
    → DeactivateOrderHandler
    → ReactivateOrderHandler
    → InitiateCheckoutHandler
    → RequestPaymentHandler
    → CompleteOrderHandler
    → CancelOrderHandler
    → EndSessionHandler
    → StartNewOrderOfflineHandler
    → SyncOrderOnlineHandler

SimpleCommandBus
```

---

## Service: `terminal`

### Commands

#### `register`

Register a new POS terminal for a branch.

```bash
./demo terminal register --name="Cashier 1" [--branch-id=<uuid>]
```

Options:
- `--name=<string>` — Terminal display name (required)
- `--branch-id=<uuid>` — Branch UUID (optional; auto-generated when omitted)

Output:
```
Terminal registered successfully.
  Terminal ID   <uuid>
  Branch ID     <uuid>
  Name          Cashier 1
  Status        active
```

---

#### `activate`

Set terminal status to Active.

```bash
./demo terminal activate --terminal-id=<uuid>
```

---

#### `disable`

Set terminal status to Disabled.

```bash
./demo terminal disable --terminal-id=<uuid>
```

---

#### `maintenance`

Set terminal to Maintenance mode.

```bash
./demo terminal maintenance --terminal-id=<uuid>
```

---

#### `rename`

Rename a terminal.

```bash
./demo terminal rename --name="New Name" [--terminal-id=<uuid>]
```

---

#### `reassign`

Move a terminal to another branch (refused while the terminal is active — disable or set to maintenance first).

```bash
./demo terminal reassign --branch-id=<uuid> [--terminal-id=<uuid>]
```

---

#### `decommission`

Permanently retire a terminal (refused while active). A decommissioned terminal only accepts `recommission`.

```bash
./demo terminal decommission --reason="end of life" [--terminal-id=<uuid>]
```

---

#### `recommission`

Return a decommissioned terminal to service; it comes back `disabled` and needs `activate`.

```bash
./demo terminal recommission --reason="back in service" [--terminal-id=<uuid>]
```

---

#### `get`

Display terminal details from the read model.

```bash
./demo terminal get --terminal-id=<uuid>
```

Output:
```
Terminal Details
  Terminal ID   <uuid>
  Branch ID     <uuid>
  Name          Cashier 1
  Status        active
  Registered    2026-02-18T10:00:00+00:00
```

---

#### `list`

List all terminals (optionally filtered by branch or status).

```bash
./demo terminal list [--branch-id=<uuid>] [--status=active|disabled|maintenance]
```

---

## Service: `shift`

### Commands

#### `open`

Open a new cashier shift on a terminal.

```bash
./demo shift open --opening-cash=10000 [--terminal-id=<uuid>] [--branch-id=<uuid>] [--cashier-id=<uuid>]
```

Options:
- `--terminal-id=<uuid>` — Terminal UUID (defaults to the last registered terminal)
- `--branch-id=<uuid>` — Branch UUID (defaults to the last used branch)
- `--cashier-id=<uuid>` — Cashier UUID (optional; auto-generated when omitted)
- `--opening-cash=<int>` — Opening cash amount in minor units (e.g. `10000` = 100.00)
- `--currency=<string>` — Currency code (default `PHP`)

Output:
```
Shift opened successfully.
  Shift ID      <uuid>
  Terminal ID   <uuid>
  Cashier ID    <uuid>
  Opening Cash  PHP 100.00
  Opened At     2026-02-18T10:00:00+00:00
```

---

#### `assign`

Hand the shift to an operating cashier, optionally naming fallback cashiers
(at most 3). The shift's cashier slot moves to the assignee only once the
change is saved, so a cashier who already operates another open shift is
refused.

```bash
./demo shift assign --assignee-id=<uuid> [--shift-id=<uuid>] [--fallback-ids=<uuid>,<uuid>]
```

Options:
- `--assignee-id=<uuid>` — Cashier who will operate the shift (defaults to the last opened shift's cashier)
- `--shift-id=<uuid>` — Shift UUID (defaults to the last opened shift)
- `--fallback-ids=<uuid>,<uuid>` — Comma-separated fallback cashiers, max 3

Re-issuing an assignment REPLACES the membership — it does not add to it.

---

#### `unassign`

Clear the membership, handing operation back to the cashier who opened the
shift. Refused when that opener meanwhile operates another open shift.

```bash
./demo shift unassign [--shift-id=<uuid>]
```

Options:
- `--shift-id=<uuid>` — Shift UUID (defaults to the last opened shift)

---

#### `close`

Close a shift with declared cash amount.

```bash
./demo shift close --shift-id=<uuid> --declared-cash=9500
```

Options:
- `--shift-id=<uuid>` — Shift UUID (required)
- `--declared-cash=<int>` — Declared closing cash in minor units (required)
- `--currency=<string>` — Currency code (default `PHP`)

Output:
```
Shift closed successfully.
  Shift ID        <uuid>
  Declared Cash   PHP 95.00
  Expected Cash   PHP 100.00
  Variance        PHP -5.00
  Closed At       2026-02-18T18:00:00+00:00
```

---

#### `force-close`

Force-close a shift with supervisor authorization.

```bash
./demo shift force-close --shift-id=<uuid> --supervisor-id=<string> --reason="End of day emergency"
```

---

#### `cash-drop`

Record a cash drop (cash removed from drawer).

```bash
./demo shift cash-drop --shift-id=<uuid> --amount=5000 [--currency=PHP]
```

Output:
```
Cash drop recorded.
  Shift ID    <uuid>
  Amount      PHP 50.00
  Recorded At 2026-02-18T14:00:00+00:00
```

---

#### `reconcile`

Rebuild the shift-slot file from the shifts the event store says are open —
the recovery step for the "slot state is uncertain" error, which a command
raises when it persisted a shift but could not update its slots (or was
killed between claiming a cashier and committing the change). Without it a
terminal or a cashier can stay blocked by a shift that is not open.

```bash
./demo shift reconcile
```

Output:
```
▶ Shift Slot Reconciliation
✓ Corrected 2 slot entries.
  Open shifts holding slots: 1
```

It discards in-flight claims, so run it only when no other demo command is
running.

---

## Service: `session`

### Commands

#### `start`

Start a new POS session for a shift.

```bash
./demo session start --shift-id=<uuid> --terminal-id=<uuid>
```

Output:
```
Session started.
  Session ID    <uuid>
  Shift ID      <uuid>
  Terminal ID   <uuid>
  State         idle
```

---

#### `new-order`

Start a new draft order in the active session.

```bash
./demo session new-order --session-id=<uuid>
```

Output:
```
New order started.
  Session ID    <uuid>
  Order ID      <uuid>
  State         building
```

---

#### `park`

Park the currently active order. Refused once checkout has started (and
after payment) — parking then would strand the confirmed inventory
reservation.

```bash
./demo session park --session-id=<uuid>
```

---

#### `resume`

Resume a parked order.

```bash
./demo session resume --session-id=<uuid> --order-id=<uuid>
```

---

#### `deactivate`

Deactivate the active order (simulates the `DraftLifecycleService` TTL
expiry). Refused once checkout has started (and after payment), matching the
sweep's skip behavior.

```bash
./demo session deactivate --session-id=<uuid> [--reason=<text>]
```

---

#### `reactivate`

Reactivate an inactive order (re-reserves inventory).

```bash
./demo session reactivate --session-id=<uuid> --order-id=<uuid>
```

---

#### `checkout`

Initiate checkout for the active order (Draft → Confirmed).

```bash
./demo session checkout --session-id=<uuid>
```

Output:
```
Checkout initiated.
  Session ID    <uuid>
  Order ID      <uuid>
  State         checkout
  Reservation   converted to hard
  Order         confirmed via Ordering BC
```

---

#### `pay`

Request payment for the active order.

```bash
./demo session pay --session-id=<uuid> --amount=15000 --method=cash [--currency=PHP]
```

Options:
- `--method=<string>` — Payment method: `cash`, `card`, `gcash`, etc.

Output:
```
Payment requested.
  Session ID      <uuid>
  Order ID        <uuid>
  Amount          PHP 150.00
  Method          cash
  Authorization   OK
```

---

#### `complete`

Complete the order (mark as fully paid and done).

```bash
./demo session complete --session-id=<uuid>
```

Output:
```
Order completed.
  Session ID    <uuid>
  Order ID      <uuid>
  State         idle
  Inventory     deducted
```

---

#### `cancel`

Cancel the active order. Allowed while building and during checkout before
any payment; refused once payment has been received — a paid order can only
be completed (post-payment cancellation is a downstream sales-order
operation).

```bash
./demo session cancel --session-id=<uuid> --reason="Customer changed mind"
```

---

#### `end`

End the session (called when shift is closing).

```bash
./demo session end --session-id=<uuid>
```

---

#### `new-order-offline`

Create a draft order while offline (queued for sync).

```bash
./demo session new-order-offline --session-id=<uuid> --command-id=<uuid>
```

Options:
- `--command-id=<uuid>` — Idempotency key for the offline command (required)

Output:
```
Offline order created.
  Session ID    <uuid>
  Order ID      <uuid>
  Command ID    <uuid>
  Queued for sync.
```

---

#### `sync`

Sync an offline-created order to the Ordering BC.

```bash
./demo session sync --session-id=<uuid> --order-id=<uuid>
```

Output:
```
Order synced online.
  Session ID    <uuid>
  Order ID      <uuid>
  Draft created in Ordering BC.
  Removed from pending sync queue.
```

---

## Scenarios

Scripted end-to-end scenarios live in `demo/scenarios/` as numbered,
self-contained bash scripts (`01-…` through `07-…`). Each starts with
`./demo/demo state clear` and drives the CLI step by step.

---

### `01-full-shift-lifecycle.sh`

**Title:** Full Shift Lifecycle

**Demonstrates:**
1. Register a terminal
2. Open a shift with opening cash
3. Start a session
4. Start a new order
5. Initiate checkout
6. Request payment (cash)
7. Complete the order
8. Record a cash drop
9. End the session
10. Close the shift with declared cash and variance

**Expected outcome:** Shift closed with variance recorded; all events emitted in correct sequence.

---

### `02-checkout-flow.sh`

**Title:** Checkout and Payment Flow

**Demonstrates:**
1. Register terminal, open shift, start session
2. Start new order
3. Initiate checkout (soft → hard reservation)
4. Request payment — authorized OK
5. Complete order (inventory deducted)

**Expected outcome:** Full checkout cycle from Draft to Completed.

---

### `03-park-and-resume.sh`

**Title:** Park Order and Resume

**Demonstrates:**
1. Start session, start order A
2. Park order A
3. Start order B
4. Park order B
5. Resume order A
6. Complete order A
7. Resume order B
8. Complete order B

**Expected outcome:** Two orders managed concurrently via park/resume; session returns to Idle after each completion.

---

### `04-draft-ttl-expiry.sh`

**Title:** Draft TTL Expiry and Reactivation

**Demonstrates:**
1. Setup (terminal, shift, session), start new order
2. Simulate TTL expiry — `session deactivate` (what `DraftLifecycleService`
   would dispatch in production)
3. Reactivate the order — inventory re-reservation succeeds
4. Proceed to checkout, pay, and complete

**Expected outcome:** Deactivated order reactivates and completes normally.

---

### `05-force-close-shift.sh`

**Title:** Supervisor Force-Close

**Demonstrates:**
1. Open shift, start session, start order
2. Attempt normal shift close — fails (active order exists)
3. Supervisor force-closes the shift with reason
4. Shift is ForceClosed; order remains unresolved (logged)

**Expected outcome:** Normal close blocked by invariant; force-close succeeds with audit trail.

---

### `06-offline-sync.sh`

**Title:** Offline Order Creation and Sync

**Demonstrates:**
1. Setup (terminal, shift, session)
2. Create two orders offline (`StartNewOrderOffline`; each is auto-queued
   for sync)
3. Network restored — sync each order online (`SyncOrderOnline`)
4. Continue selling with a fresh online order (synced orders now live in
   the Ordering BC; the POS session no longer tracks them)

**Expected outcome:** Both offline orders queue and sync independently;
normal online flow resumes afterward.

---

### `07-concurrency-conflict.sh`

**Title:** Optimistic Locking Conflict

**Demonstrates:**
1. Register terminal
2. Load terminal at version 1 (instance A)
3. Load terminal at version 1 (instance B)
4. Instance A activates terminal — stored at version 2
5. Instance B attempts to disable terminal at expected version 1
6. `ConcurrencyException` thrown and displayed

**Expected outcome:** Conflict detected; second write rejected with clear error message.

---

## Output Format

All CLI output follows the inventory demo style:

```
  FieldName     value
```

Errors:
```
ERROR: <message>
```

Domain errors:
```
Domain error: <InvariantViolationException message>
```

Concurrency errors:
```
Concurrency conflict: <ConcurrencyException message>
```

---

## Configuration

No config file was implemented (the spec's `config.json.dist` idea was
dropped). Defaults live in the CLI handlers: currency defaults to `PHP`
via `--currency`, and ids default to the `last_*` entries in
`demo/data/demo-state.json`.

---

## Data Persistence

Events are persisted to a JSON file via `FileEventStore` (`demo/cli/FileEventStore.php`). Each demo command appends events to the file (merge-on-write under an exclusive lock), enabling stateful multi-command sessions.

All three stores (`FileEventStore`, `StateStore` and `FileShiftSlotReservation`) write defensively: mutations re-read the current file under a sidecar `.lock` (so concurrent commands never lose each other's writes), the new content goes to a `.tmp` file that is atomically renamed over the store, and every persistence failure throws instead of reporting success. A corrupt or unreadable file also fails loudly rather than silently loading as empty. The recovery for a corrupt store is `./demo/demo state clear`, which is handled before bootstrap (so it works even when the stores can't be loaded) and resets all three files (events, ids, shift slots) as a coordinated all-or-nothing operation.

One corruption cannot be repaired in place: a history where two events of the same aggregate claim the same version, which an older build could write when two commands raced. Any command touching that aggregate says so and names `state clear` as the remedy; commands on every other aggregate keep working.

Shift slots live in a third store, `demo/data/shift-slots.json` (same lock/tmp-rename discipline), because one-shift-per-terminal and one-shift-per-cashier need a claim that survives across processes — see `./demo shift reconcile` above for its recovery step.

Data files (fixed): `demo/data/events.json`, `demo-state.json` and `shift-slots.json` — all git-ignored, all cleared together by `./demo/demo state clear`. (Tests point the CLI at a scratch directory via the `POS_DEMO_DATA_DIR` environment variable; normal demo usage never sets it.)

Scenario scripts start with `state clear` instead of using per-run data files.

---

## Implementation Notes

> Status of the original design decisions, now that the demo is implemented.

- `FileEventStore` implements the `EventStore` interface from `dranzd/common-event-sourcing` (spec's `JsonFileEventStore` name was not kept)
- `IdResolver` (short aliases like `terminal-1`) was **not implemented** — the state file's `last_*` defaults cover the same need
- `Output` follows the same API as the inventory demo `Output` class
- Stub services (`StubOrderingService`, etc.) live in `tests/Stub/Service/`
- `IdempotencyRegistry` and `PendingSyncQueue` live in `src/`; the demo bootstrap rebuilds both from persisted events on every invocation
- A `./utils demo` subcommand was **not added** — run `./demo/demo` directly (inside the container: `./utils exec php demo/demo …`)

---

**Last Updated:** August 16, 2026 (synced to the implemented demo)
**Status:** Implemented — see `demo/` and `demo/README.md` for usage
