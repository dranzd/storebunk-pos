<!-- hash: 46783336fb81fe2ffa35ec31cfd7dc93812f4a007ef0ee5048ef2d4be3479781 -->
# hexagonal-architecture-principles

Category: architecture
Status: stable
Source: storebunk-pos

---

The system must follow strict Hexagonal Architecture (Ports & Adapters) combined with DDD, Event Sourcing, and CQRS. The core is a framework-agnostic library with domain-centric design and event-driven state changes.

The system follows a strict **Hexagonal Architecture (Ports & Adapters)** combined with **Domain-Driven Design (DDD)**, **Event Sourcing (ES)**, and **CQRS**.

### Principles

1. **Library-First**: The core system is a PHP library. It has NO dependencies on web frameworks (Laravel, Symfony, etc.) or UI components.
2. **Domain-Centric**: The heart of the system is the Domain Model, free from infrastructure concerns.
3. **Event-Driven**: State changes are captured as domain events. Aggregates are reconstituted from events (Event Sourcing).
4. **Bounded Context**: POS is a first-class bounded context that orchestrates but never owns business truth (pricing, stock, payments, accounting).
5. **Operational Domain**: POS enforces operational discipline — terminal lifecycle, shift accountability, cash tracking, checkout boundaries.

---

## Source File
docs/core_design.md
