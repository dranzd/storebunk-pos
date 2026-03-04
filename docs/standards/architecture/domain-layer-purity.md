<!-- hash: eb6ac1a3d5d4c66f5f9f85e0e2045498ecc41726d84ee2d0cf2e826b5c5bd9f5 -->
# domain-layer-purity

Category: architecture
Status: stable
Source: storebunk-pos

---

The domain layer must contain only pure business logic with no framework dependencies. It should encapsulate aggregates, value objects, events, repository interfaces, and service interfaces that define business contracts.

### Domain Layer
- **Pure business logic** — No framework dependencies
- **Aggregates** enforce business rules and invariants
- **Value Objects** are immutable and self-validating
- **Events** represent facts that happened
- **Repository Interfaces** define contracts without implementation
- **Service Interfaces** define ports to external bounded contexts
- **Read Model Interfaces** define query contracts

---

## Source File
docs/folder-structure.md
