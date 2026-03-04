<!-- hash: a873ece6278f585e096edc360527a05a1f2ffab9e47cb67930e8a73447f03cc9 -->
# aggregate-invariant-enforcement

Category: ddd
Status: stable
Source: storebunk-pos

---

All business invariants must be enforced within aggregate roots. Aggregates are transaction boundaries and must protect business rules through their public methods. No invariant enforcement may be delegated to application services.

### 1.1 Terminal (Entity / Lightweight Aggregate)

Represents a registered POS device.

#### Fields

| Field | Type | Description |
|-------|------|-------------|
| `terminalId` | `TerminalId` (VO) | Unique identifier |
| `branchId` | `BranchId` (VO) | Branch this terminal belongs to |
| `name` | `string` | Human-readable terminal name |
| `status` | `TerminalStatus` (Enum) | Active, Disabled, Maintenance, Decommissioned |
| `registeredAt` | `DateTimeImmutable` | When the terminal was registered |

#### Invariants

---

## Source File
docs/domain-model.md
