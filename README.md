# StoreBunk POS

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

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
- **PosSession** — Active UI lifecycle managing order flow (Idle, Building, Checkout)

### Key Invariants

1. One cashier = one terminal per open shift
2. One terminal = one open shift
3. Shift cannot close with unresolved orders
4. Checkout locks order lines
5. Confirmed orders never auto-expire
6. Cash variance is recorded, never silently corrected

## Domain Events

**Emits:**
- `ShiftOpened` / `ShiftClosed` / `ShiftForceClosed`
- `CashDropRecorded`
- `CheckoutInitiated`
- `OrderCompleted` / `OrderCancelledViaPOS`
- `PaymentRequested`

**Reacts to (from other domains):**
- Ordering BC — Order state changes
- Inventory BC — Reservation expiration
- Payment BC — Authorization results

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
