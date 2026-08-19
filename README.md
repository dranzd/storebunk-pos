# StoreBunk POS

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-3.1.0-brightgreen.svg)](CHANGELOG.md)

> **POS is the operational execution layer that enforces discipline, protects integrity, and coordinates business truth across retail transactions — without owning any of it.**

A PHP library for managing Point of Sale operations in the StoreBunk Multi-Retail Platform. This library governs terminal lifecycle, cashier shifts, POS sessions, checkout orchestration, payment delegation, and operational cash tracking.

## Domain Purpose

The POS Bounded Context is the **operational execution layer of retail transactions**. It is responsible for:

- Managing terminals
- Managing cashier shifts
- Managing POS sessions
- Coordinating order creation (draft to committed)
- Orchestrating payment requests
- Tracking operational cash drawer state
- Enforcing multi-terminal discipline

POS does **NOT** own:

- Price calculation or tax computation (Ordering BC)
- Inventory stock or reservation logic (Inventory BC)
- Payment processing or gateway logic (Payment BC)
- Ledger posting or financial reconciliation (Financial BC)

POS **orchestrates**. It does not calculate. It does not post.

## Architecture

This library is built using:

- **Domain-Driven Design (DDD)** — Rich domain models with business logic
- **Event Sourcing (ES)** — All state changes captured as events
- **Hexagonal Architecture (Ports & Adapters)** — Framework-agnostic core
- **CQRS** — Separate read and write models

## Requirements

- PHP 8.3 or higher
- Composer
- Docker & Docker Compose (for development)

## Installation

### For Library Usage

Install via Composer:

```bash
composer require dranzd/storebunk-pos
```

### For Development

```bash
# 1. Clone repository
git clone git@github.com:dranzd/storebunk-pos.git
cd storebunk-pos

# 2. Build Docker container
./utils rebuild

# 3. Install dependencies
./utils install

# 4. Run tests
./utils test
```

## Key Concepts

### Separation of Commitment

| Phase | Reservation | TTL | Editable | Auto-Expire |
|-------|------------|-----|----------|-------------|
| **Draft** | Soft | Yes | Yes | Yes (inactivity) |
| **Confirmed** | Hard | No | No | Never |
| **Completed** | Deducted | N/A | No | N/A |

### Core Aggregates

- **Terminal** — Registered POS device with lifecycle (Active, Disabled, Maintenance)
- **Shift** — Cashier working session with cash handling and close policies
- **PosSession** — Active UI lifecycle managing order flow (Idle, Building, Checkout, Payment)

### Key Invariants

1. One cashier = one terminal per open shift
2. One terminal = one open shift
3. Shift cannot close with unresolved orders
4. Checkout locks order lines
5. Confirmed orders never auto-expire
6. Cash variance is recorded, never silently corrected
7. Payment received = the order can only be completed, never cancelled by the POS

**Invariants 1 and 2 need something from you.** They span aggregates, so
per-aggregate locking cannot enforce them. The five slot-holding shift
handlers take a `ShiftSlotReservationInterface`, and your implementation of
it MUST be atomic against concurrent callers — a database unique constraint,
`SETNX`, an advisory lock. A read model or a process-local lock is not a
valid backing. The bundled `InMemoryShiftSlotReservation` is a single-process
reference; the demo ships a file-locked one you can read as a worked example.

**Offline sync needs something from you too.** `IdempotencyRegistry` and
`PendingSyncQueue` are plain in-memory objects; a host that runs more than one
process must persist them or rebuild them from events on start. When
rebuilding the registry, pass the same purpose the handler would — it is a
required argument, and `IdempotencyRegistry::purposeFor()` builds it. Getting
it wrong is silent: either a reused command id is absorbed (reporting success
for work never done) or a legitimate retry is refused.
`Dranzd\StorebunkPos\Demo\Cli\OfflineStateReplay` is a worked example.

Because the reservation and the event store are two stores, a host that wants
them to commit or fail as one implements the port inside its own unit of
work. Without that, what the protocol guarantees is that every reachable
intermediate state over-blocks rather than over-permits, and is recoverable
via the port's `reconcile()`. See
[issue 8003](docs/reported-issues/8000-concurrency/8003-shift-enforcement-not-atomic.md).

## Domain Events

**Terminal:**
- `TerminalRegistered` / `TerminalActivated` / `TerminalDisabled` / `TerminalMaintenanceSet`

**Shift:**
- `ShiftOpened` / `ShiftClosed` / `ShiftForceClosed` / `CashDropRecorded`

**PosSession (online):**
- `SessionStarted` / `SessionEnded`
- `NewOrderStarted` / `OrderParked` / `OrderResumed`
- `OrderDeactivated` / `OrderReactivated`
- `CheckoutInitiated` / `PaymentRequested`
- `OrderCompleted` / `OrderCancelledViaPOS`

**PosSession (offline/sync):**
- `OrderCreatedOffline` / `OrderMarkedPendingSync` / `OrderSyncedOnline`

**Reacts to (from other domains via service ports):**
- Ordering BC — via `OrderingServiceInterface`
- Inventory BC — via `InventoryServiceInterface`
- Payment BC — via `PaymentServiceInterface`

## Development

### Available Commands

```bash
./utils up            # Start Docker containers
./utils down          # Stop Docker containers
./utils shell         # Open shell in PHP container
./utils test          # Run PHPUnit tests
./utils phpstan       # Run static analysis
./utils cs-check      # Check code style
./utils quality       # Run all quality checks
```

## Documentation

For detailed documentation, see the [docs](docs/) directory:

- **[Domain Vision](docs/domain-vision.md)** — Business context, philosophy, and boundaries
- **[Architecture Guide](docs/architecture.md)** — DDD, ES, Hexagonal, CQRS patterns
- **[Domain Model](docs/domain-model.md)** — Aggregates, Commands, Events, Policies
- **[Core Design](docs/core_design.md)** — Architectural principles and layers
- **[Technical Design](docs/technical_design.md)** — Implementation details and coding standards
- **[Folder Structure](docs/folder-structure.md)** — Complete directory reference
- **[Milestones](docs/milestones.md)** — Phased roadmap with commit messages
- **[Feature Specifications](docs/features/README.md)** — Implementation checklist with status
- **[Demo CLI Specification](docs/demo.md)** — Demo CLI design and scenario specifications

## Context Boundaries

```
POS BC
   -> uses Ordering BC
   -> uses Inventory BC
   -> uses Payment BC

Ordering, Inventory, Payment
   -> never depend on POS
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Authors

- **dranzd** — *Initial work*

## Acknowledgments

- Built for the StoreBunk ecosystem
- Inspired by modern PHP library best practices
- Reference architecture from storebunk-inventory
