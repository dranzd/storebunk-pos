# POS Bounded Context – Domain Vision

**Context:** StoreBunk Multi-Retail Platform
**Domain:** Point of Sale (POS)
**Type:** Operational Interface Domain
**Audience:** Product Owners, Domain Experts, Architects

---

## 1. Domain Purpose

The **POS (Point of Sale) Bounded Context** is the **operational execution layer of retail transactions**.

It is responsible for:

- Managing terminals
- Managing cashier shifts
- Managing POS sessions
- Coordinating order creation
- Transitioning orders from draft to committed
- Orchestrating payment requests
- Tracking operational cash drawer state

It answers the key business question:
> "What is happening right now at the point of sale, and is it operationally sound?"

---

## 2. What POS Is NOT

POS is not an accounting system.
POS is not an inventory system.
POS is not an order calculation engine.

POS is the **interaction and operational control domain** of brick-and-mortar retail.

It orchestrates. It does not calculate. It does not post.

Business truth belongs to:

| Domain | Responsibility |
|--------|---------------|
| **Ordering BC** | Price calculation, tax computation, order totals |
| **Inventory BC** | Stock levels, reservation handling, deduction |
| **Payment BC** | Authorization, capture, gateway logic |
| **Financial BC** | Revenue recognition, ledger posting, reconciliation |

POS only coordinates their usage in real time.

---

## 3. Core Philosophy

### 3.1 Separation of Commitment

Retail transactions have two distinct phases:

**Soft Commitment (Draft)**
- Items are scanned
- Inventory is softly reserved (TTL applies)
- Order may expire due to inactivity

**Hard Commitment (Checkout / Confirmed)**
- Order is confirmed
- Reservation becomes hard (no TTL)
- No automatic expiration
- Explicit cancellation required

This separation ensures:
- Operational flexibility during scanning
- Financial discipline once checkout begins
- Deterministic behavior across terminals

### 3.2 POS Is a Real Domain

POS is not a thin UI wrapper. It has its own domain model:

- Terminal lifecycle
- Shift lifecycle
- Session lifecycle
- Cash handling rules
- Reservation timing policies
- Checkout boundary enforcement

This makes POS a **first-class bounded context** within the retail OS.

### 3.3 Operational Discipline Over Automation

The system is designed with the assumption that:

- Humans make mistakes
- Hardware fails
- Internet may drop
- Busy seasons cause stress

Therefore:

- Nothing critical auto-deletes
- Post-payment orders never auto-expire
- Confirmed orders require explicit resolution
- Shift close blocks unresolved commitments
- Cash variance is recorded, not silently corrected

Where automation becomes dangerous, manual intervention is allowed and logged.

---

## 4. Business Context

POS sits at the operational front of retail, connecting multiple domains:

- **Ordering** — POS creates and confirms sales orders
- **Inventory** — POS triggers soft/hard reservations and releases
- **Payment** — POS requests authorization and acts on OK/NOT OK
- **Financial** — Downstream consumers react to POS events (ShiftClosed, etc.)
- **Identity/Auth** — POS authenticates cashiers and enforces terminal assignment

Within StoreBunk, POS is an **operational interface domain** that exposes its state and events to other modules without owning their business logic.

---

## 5. Business Objectives

| Objective | Description |
|-----------|-------------|
| **Operational Control** | Enforce terminal, shift, and session discipline at the point of sale |
| **Transactional Integrity** | Separate draft and committed phases with clear boundaries |
| **Concurrency Safety** | Handle multi-terminal, shared-inventory retail safely |
| **Cash Accountability** | Track operational cash movements with variance recording |
| **Auditability** | Every state change is an event; every action is traceable |
| **Modularity** | POS delegates business truth; it never owns pricing, stock, or payments |

---

## 6. Multi-Terminal Retail Reality

The system assumes:

- Multiple terminals per branch
- Concurrent selling
- Shared inventory pool
- Independent shifts

Therefore:

- Inventory is server-authoritative
- Reservations occur at draft stage
- Reservation converts to hard at checkout
- Conflicts fail fast
- Draft restoration is terminal-bound

Concurrency safety is prioritized over UI convenience.

---

## 7. Checkout as Commitment Boundary

In this design: **Checkout = Order Confirmation**.

Once checkout is triggered:

- Cart is locked
- Reservation becomes hard
- TTL stops
- Explicit cancel required

This prevents ambiguous "half-committed" states. There is no limbo state between draft and confirmed.

---

## 8. Payment as External Authority

POS only receives **OK** or **NOT OK** from the Payment BC.

POS never:

- Stores authorization logic
- Handles gateway retries
- Computes capture state

This keeps payment logic isolated and replaceable.

---

## 9. Cash Drawer Is Operational, Not Financial

POS tracks:

- Opening float
- Cash payments
- Cash refunds
- Cash drops
- Closing declaration
- Variance

It does not:

- Post accounting entries
- Record expenses
- Perform reconciliation

Cash handling is operational. Accounting is external.

---

## 10. Explicit Boundaries Create Stability

Key invariants:

1. One cashier = one terminal per shift
2. One terminal = one open shift
3. Shift cannot close with unresolved orders
4. Draft may expire
5. Confirmed never auto-expires
6. Inventory deducts only on completion
7. No expense withdrawals in POS

These invariants prevent state corruption.

---

## 11. Designed for Extension, Not Over-Engineering

The POS core intentionally excludes:

- Exchange workflows
- Advanced return logic
- Refund without return
- Layaway time policies
- Accounting reconciliation
- Cross-terminal recovery

The architecture supports them, but they are not part of the foundational scope.

**Stability first. Extensibility second. Complexity later.**

---

## 12. Architectural Position in Retail OS

```
POS BC
   -> uses Ordering BC
   -> uses Inventory BC
   -> uses Payment BC

Ordering, Inventory, Payment
   -> never depend on POS
```

This preserves modularity. POS is an operational interface domain, not a central business authority.

---

## 13. Domain Value to the Business

- **Enforces operational discipline** at the point of sale
- **Protects transactional integrity** across commitment phases
- **Handles concurrency safely** in multi-terminal environments
- **Tracks cash operationally** with variance accountability
- **Delegates business truth outward** to specialized domains
- **Avoids accounting contamination** in operational workflows
- **Supports multi-terminal scaling** for growing retail operations

---

## 14. Example Real-World Use Cases

| Scenario | Description |
|----------|-------------|
| **Single-Branch Retail** | One store, 2-3 terminals, shared inventory, independent shifts |
| **Multi-Branch Chain** | Multiple stores, branch-scoped inventory, centralized reporting |
| **Franchise Network** | Independent branches with standardized POS operations |
| **Pop-Up / Event Sales** | Temporary terminals with offline-capable cash-only mode |
| **High-Volume Retail** | Busy seasons with concurrent terminals and fast checkout |

---

## 15. Domain Vision Summary

> **POS is the operational execution layer that enforces discipline, protects integrity, and coordinates business truth across retail transactions — without owning any of it.**

It is designed to be:

- **Deterministic** — clear state transitions, no ambiguity
- **Auditable** — every change is an event
- **Concurrent-safe** — multi-terminal by design
- **Operationally realistic** — accounts for human error and hardware failure
- **Modular** — delegates to specialized bounded contexts

---

## See Also

- [Architecture Guide](architecture.md) — DDD, Event Sourcing, Hexagonal Architecture, CQRS patterns
- [Domain Model](domain-model.md) — Aggregates, Commands, Events, Policies
- [Core Design](core_design.md) — Architectural principles and layers
- [Technical Design](technical_design.md) — Implementation details and coding standards
- [Features](features/README.md) — Phased implementation checklist
