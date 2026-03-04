<!-- hash: a4e2e41958597a53c88d6d94c76760e4cb845290830c98c6d787f724407a8be1 -->
# event-sourcing-projections

Category: event-sourcing
Status: stable
Source: storebunk-pos

---

Event Store is the single source of truth. Read models are built by listening to domain events, enabling CQRS. Aggregates must NOT have public getters - all reads go through projections.

- **Source of Truth**: The Event Store is the single source of truth.
- **Projections**: Read models (e.g., `ShiftCashSummary`, `ActiveOrders`) are built by listening to domain events. This enables CQRS (Command Query Responsibility Segregation).
- **No Public Getters on Aggregates**: Aggregate roots must NOT have public getters for querying state. All reads go through projections.

---

## Source File
docs/core_design.md
