<!-- hash: 956d2fcc964346e03149ae7d8a0915e0f2212d17bbe2ad64c0b932b70f92fd87 -->
# bus-integration-pattern

Category: architecture
Status: stable
Source: storebunk-pos

---

The core must expose Command Bus for writes, Query Bus for reads, and Event Bus for side effects. External BCs are accessed through Service Interfaces (Ports) where consumers provide real adapters.

- The core exposes a **Command Bus** for write operations.
- The core exposes a **Query Bus** for read operations.
- Events are dispatched to an **Event Bus** for side effects and projections.
- External BCs are accessed through **Service Interfaces (Ports)** — consumers provide real adapters.

---

## Source File
docs/technical_design.md
