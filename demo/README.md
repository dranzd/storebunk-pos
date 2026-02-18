# StoreBunk POS Demo CLI

Interactive command-line demonstration of the StoreBunk POS library v1.0.0.

## Requirements

- PHP 8.3+
- Composer dependencies installed (`composer install`)

## Quick Start

```bash
# Show available commands
./demo/demo

# Register a terminal
./demo/demo terminal register --name="POS-01"

# Open a shift
./demo/demo shift open --opening-cash=50000

# Start a session
./demo/demo session start

# Start a new order
./demo/demo session new-order
```

## Architecture

The demo uses:
- **In-memory event store** - All events stored in memory (lost on exit)
- **JSON state file** - Persists IDs between invocations (`demo/data/demo-state.json`)
- **Stub services** - Mock implementations of BC ports (Ordering, Inventory, Payment)
- **Command bus** - Routes commands to handlers
- **Read models** - Projections for queries (Terminal only)

**⚠️ CRITICAL LIMITATION**: The event store is in-memory only. Each CLI invocation starts a fresh PHP process with an empty event store. This means:
- Aggregates created in one command invocation **do not exist** in subsequent invocations
- The scenario scripts will **not work** as written because they call the demo multiple times
- For testing, you must either:
  1. Modify the demo to use a persistent event store (e.g., file-based or database)
  2. Run all commands in a single PHP session (not currently supported)
  3. Use the demo for single-command testing only

The JSON state file only persists **IDs**, not the actual aggregate state or events.

## Services

### Terminal Service

Manages POS terminal lifecycle.

```bash
# Register a new terminal
./demo/demo terminal register --name="POS-01" [--branch-id=<uuid>]

# Activate terminal
./demo/demo terminal activate [--terminal-id=<uuid>]

# Disable terminal
./demo/demo terminal disable [--terminal-id=<uuid>]

# Set maintenance mode
./demo/demo terminal maintenance [--terminal-id=<uuid>]

# Get terminal details
./demo/demo terminal get [--terminal-id=<uuid>]

# List all terminals
./demo/demo terminal list [--branch-id=<uuid>] [--status=<active|disabled|maintenance>]
```

### Shift Service

Manages cashier shifts and cash handling.

```bash
# Open a shift
./demo/demo shift open --opening-cash=<amount> [--terminal-id=<uuid>] [--branch-id=<uuid>] [--cashier-id=<uuid>] [--currency=PHP]

# Close a shift
./demo/demo shift close --declared-cash=<amount> [--shift-id=<uuid>] [--currency=PHP]

# Force close a shift (supervisor)
./demo/demo shift force-close [--shift-id=<uuid>] [--supervisor-id=<id>] [--reason=<text>]

# Record cash drop
./demo/demo shift cash-drop --amount=<amount> [--shift-id=<uuid>] [--currency=PHP]
```

### Session Service

Manages POS sessions and order lifecycle.

```bash
# Start a POS session
./demo/demo session start [--shift-id=<uuid>] [--terminal-id=<uuid>]

# Start a new order
./demo/demo session new-order [--session-id=<uuid>]

# Park current order
./demo/demo session park [--session-id=<uuid>]

# Resume a parked order
./demo/demo session resume --order-id=<uuid> [--session-id=<uuid>]

# Reactivate an inactive order (re-reserve inventory)
./demo/demo session reactivate --order-id=<uuid> [--session-id=<uuid>]

# Initiate checkout (confirm order, convert reservation)
./demo/demo session checkout [--session-id=<uuid>]

# Request payment
./demo/demo session pay --amount=<amount> [--method=cash|card] [--session-id=<uuid>] [--currency=PHP]

# Complete order (deduct inventory)
./demo/demo session complete [--session-id=<uuid>] [--order-id=<uuid>]

# Cancel order
./demo/demo session cancel [--reason=<text>] [--session-id=<uuid>]

# End session
./demo/demo session end [--session-id=<uuid>]

# Offline mode: start order without network
./demo/demo session new-order-offline [--session-id=<uuid>]

# Sync offline order to ordering BC
./demo/demo session sync --order-id=<uuid> [--session-id=<uuid>]
```

### State Management

```bash
# Clear all state
./demo/demo state clear

# Show current state
./demo/demo state show
```

## Scenarios

Pre-built scenario scripts demonstrate complete workflows:

```bash
# 1. Full shift lifecycle (register → open → orders → close)
./demo/scenarios/01-full-shift-lifecycle.sh

# 2. Checkout flow (draft → confirmed → paid → completed)
./demo/scenarios/02-checkout-flow.sh

# 3. Park and resume orders
./demo/scenarios/03-park-and-resume.sh

# 4. Draft TTL expiry and reactivation
./demo/scenarios/04-draft-ttl-expiry.sh

# 5. Force close shift (emergency)
./demo/scenarios/05-force-close-shift.sh

# 6. Offline mode and synchronization
./demo/scenarios/06-offline-sync.sh

# 7. Concurrency conflict detection
./demo/scenarios/07-concurrency-conflict.sh
```

## Money Amounts

All monetary amounts are specified in **minor units** (e.g., cents for USD, centavos for PHP):

- `--opening-cash=50000` = PHP 500.00
- `--amount=15000` = PHP 150.00
- `--declared-cash=45000` = PHP 450.00

## State Persistence

The demo uses a JSON file at `demo/data/demo-state.json` to persist:
- Last used IDs (terminal, branch, shift, cashier, session, order)
- Lists of all created IDs
- Pending sync queue for offline orders

This allows multiple CLI invocations to work with the same entities.

**Note:** The event store is in-memory only. State is lost when the process exits.

## Example Workflow

```bash
# Clear previous state
./demo/demo state clear

# Setup
./demo/demo terminal register --name="POS-Main"
./demo/demo shift open --opening-cash=50000
./demo/demo session start

# Process an order
./demo/demo session new-order
./demo/demo session checkout
./demo/demo session pay --amount=15000 --method=cash
./demo/demo session complete

# Cleanup
./demo/demo session end
./demo/demo shift close --declared-cash=65000
```

## Troubleshooting

### PHP Version Error

If you see "Your Composer dependencies require a PHP version >= 8.3.0":

```bash
# Check your PHP version
php -v

# Use a specific PHP binary if multiple versions installed
/usr/bin/php8.3 demo/demo
```

### Missing State File

The state file is created automatically on first run at `demo/data/demo-state.json`.

### Domain Errors

Domain invariant violations are displayed in red. Common errors:
- "Session must be in Idle state" - End current order first
- "Shift is not open" - Cannot close a shift that's already closed
- "Cannot reactivate order: insufficient inventory" - Inventory unavailable for re-reservation

### Concurrency Conflicts

The event store tracks aggregate versions. If you modify the same aggregate twice with the same expected version, you'll get a `ConcurrencyException`.

## Architecture Notes

This demo follows the same patterns as the production library:

- **Event Sourcing** - All state changes captured as events
- **CQRS** - Commands for writes, read models for queries
- **Hexagonal Architecture** - BC dependencies via ports (interfaces)
- **DDD** - Rich domain model with invariants enforced

The stub services simulate external bounded contexts:
- `StubOrderingService` - Order management BC
- `StubInventoryService` - Inventory reservation and deduction
- `StubPaymentService` - Payment authorization and capture

## See Also

- [Demo Specification](../docs/demo.md) - Detailed design document
- [Domain Model](../docs/domain-model.md) - Aggregates, commands, events
- [Architecture](../docs/architecture.md) - DDD, ES, CQRS patterns
