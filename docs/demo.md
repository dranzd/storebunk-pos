# StoreBunk POS — Demo CLI Specification

> **Status:** Specification only — no implementation yet.
> This document defines the design, structure, commands, and scenarios for the POS Demo CLI.
> Implementation follows the same patterns as `storebunk-inventory/demo/`.

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
3. **In-memory infrastructure** — `InMemoryEventStore`, `InMemoryTerminalRepository`, `InMemoryShiftRepository`, `InMemoryPosSessionRepository`, `InMemoryTerminalReadModel`
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
├── demo                          # Main entry point (PHP CLI script)
├── bootstrap.php                 # Wires repos, buses, stubs, event store
├── lib/
│   └── common.sh                 # Shared bash helpers (step, banner, parse_id, etc.)
├── cli/
│   ├── Output.php                # Colored terminal output helpers
│   ├── CliArgs.php               # Argument/option parser
│   ├── IdResolver.php            # Resolve short names/aliases to UUIDs
│   └── services/
│       ├── terminal.php          # Terminal service CLI handler
│       ├── shift.php             # Shift service CLI handler
│       └── session.php           # Session service CLI handler
├── infrastructure/
│   └── JsonFileEventStore.php    # JSON-backed event store for demo persistence
├── scenarios/
│   ├── full-shift-lifecycle.sh   # Complete shift open → orders → close
│   ├── checkout-flow.sh          # Draft → checkout → payment → complete
│   ├── park-and-resume.sh        # Park order, start new, resume parked
│   ├── draft-ttl-expiry.sh       # Deactivate order, reactivate with re-reservation
│   ├── force-close-shift.sh      # Supervisor force-close scenario
│   ├── offline-sync.sh           # Offline order creation and sync
│   └── concurrency-conflict.sh   # Optimistic locking conflict demonstration
└── data/
    ├── config.json.dist           # Default config (actor, currency)
    └── .gitkeep
```

---

## Bootstrap (`bootstrap.php`)

Wires the full application stack:

```
JsonFileEventStore
    → InMemoryTerminalRepository
    → InMemoryShiftRepository
    → InMemoryPosSessionRepository
    → InMemoryTerminalReadModel

StubOrderingService
StubInventoryService
StubPaymentService

IdempotencyRegistry
PendingSyncQueue
MultiTerminalEnforcementService
DraftLifecycleService

CommandRegistry (InMemoryHandlerRegistry)
    → RegisterTerminalHandler
    → ActivateTerminalHandler
    → DisableTerminalHandler
    → SetTerminalMaintenanceHandler
    → OpenShiftHandler
    → CloseShiftHandler
    → ForceCloseShiftHandler
    → RecordCashDropHandler
    → StartSessionHandler
    → StartNewOrderHandler
    → ParkOrderHandler
    → ResumeOrderHandler
    → ReactivateOrderHandler
    → InitiateCheckoutHandler
    → RequestPaymentHandler
    → CompleteOrderHandler
    → CancelOrderHandler
    → EndSessionHandler
    → StartNewOrderOfflineHandler
    → SyncOrderOnlineHandler

SimpleCommandBus
SimpleQueryBus
```

---

## Service: `terminal`

### Commands

#### `register`

Register a new POS terminal for a branch.

```bash
./demo terminal register --branch-id=<uuid> --name="Cashier 1"
```

Options:
- `--branch-id=<uuid>` — Branch UUID (required)
- `--name=<string>` — Terminal display name (required)

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
./demo shift open --terminal-id=<uuid> --branch-id=<uuid> --cashier-id=<uuid> --opening-cash=10000
```

Options:
- `--terminal-id=<uuid>` — Terminal UUID (required)
- `--branch-id=<uuid>` — Branch UUID (required)
- `--cashier-id=<uuid>` — Cashier UUID (required)
- `--opening-cash=<int>` — Opening cash amount in minor units (required, e.g. `10000` = $100.00)
- `--currency=<string>` — Currency code (default: from config, e.g. `PHP`)

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

#### `close`

Close a shift with declared cash amount.

```bash
./demo shift close --shift-id=<uuid> --declared-cash=9500
```

Options:
- `--shift-id=<uuid>` — Shift UUID (required)
- `--declared-cash=<int>` — Declared closing cash in minor units (required)
- `--currency=<string>` — Currency code (default: from config)

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

Park the currently active order.

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

Cancel the active order.

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

Scripted end-to-end scenarios live in `demo/scenarios/`. Each is a self-contained bash script using `demo/lib/common.sh` helpers.

---

### `full-shift-lifecycle.sh`

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

### `checkout-flow.sh`

**Title:** Checkout and Payment Flow

**Demonstrates:**
1. Register terminal, open shift, start session
2. Start new order
3. Initiate checkout (soft → hard reservation)
4. Request payment — authorized OK
5. Complete order (inventory deducted)

**Expected outcome:** Full checkout cycle from Draft to Completed.

---

### `park-and-resume.sh`

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

### `draft-ttl-expiry.sh`

**Title:** Draft TTL Expiry and Reactivation

**Demonstrates:**
1. Start session, start new order
2. Simulate TTL expiry — deactivate order (via `deactivateOrder`)
3. Attempt reactivation — inventory re-reservation succeeds
4. Proceed to checkout and complete

**Also demonstrates failure case:**
- Reactivation fails when `StubInventoryService` returns `false` for re-reservation
- `InvariantViolationException` is caught and displayed

**Expected outcome:** Reactivation succeeds when inventory available; fails gracefully when not.

---

### `force-close-shift.sh`

**Title:** Supervisor Force-Close

**Demonstrates:**
1. Open shift, start session, start order
2. Attempt normal shift close — fails (active order exists)
3. Supervisor force-closes the shift with reason
4. Shift is ForceClosed; order remains unresolved (logged)

**Expected outcome:** Normal close blocked by invariant; force-close succeeds with audit trail.

---

### `offline-sync.sh`

**Title:** Offline Order Creation and Sync

**Demonstrates:**
1. Start session
2. Create order offline (`StartNewOrderOffline` with commandId)
3. Mark order as pending sync
4. Reconnect — sync order online (`SyncOrderOnline`)
5. Verify idempotency: replay same sync command — no duplicate

**Also demonstrates:**
- Multiple offline orders queued and synced independently

**Expected outcome:** Offline orders created, queued, synced idempotently.

---

### `concurrency-conflict.sh`

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

`demo/data/config.json` (copy from `config.json.dist`):

```json
{
    "actor": {
        "id": "cli-user",
        "name": "CLI Demo User"
    },
    "currency": "PHP"
}
```

---

## Data Persistence

Events are persisted to a JSON file via `JsonFileEventStore`. Each demo command appends events to the file, enabling stateful multi-command sessions.

Default data file: `demo/data/demo-<timestamp>.json`

Custom data file:
```bash
./demo terminal register --name="POS 1" --data-file=my-session.json
```

Scenario scripts use a temporary data file and clean up on exit via a trap.

---

## Implementation Notes

> These are design decisions for when implementation begins.

- `JsonFileEventStore` implements the same `EventStore` interface from `dranzd/common-event-sourcing`
- `IdResolver` maps short aliases (e.g. `terminal-1`) to UUIDs stored in the data file
- `Output::php` follows the same API as the inventory demo `Output` class
- Stub services (`StubOrderingService`, etc.) are already implemented in `tests/Stub/Service/`
- `IdempotencyRegistry` and `PendingSyncQueue` are already implemented in `src/`
- The `utils` script should gain a `demo` subcommand (like inventory's `./utils demo`)

---

**Last Updated:** February 18, 2026
**Status:** Specification — awaiting implementation
