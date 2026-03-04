<!-- hash: f7700e443db6d4c173ec67e6ed02caf2b006918e3c924ed026285ba3e9867835 -->
# terminal-business-rules

Category: ddd
Status: stable
Source: storebunk-pos

---

Terminal aggregate must enforce all terminal-specific business invariants including branch assignment, status transitions, and shift constraints. These rules ensure terminal lifecycle integrity.

- Terminal belongs to exactly one branch.
- Terminal can have only one open shift at a time.
- Terminal must be Active to open a shift.
- A Decommissioned terminal cannot transition to any other status directly; it must be recommissioned first.
- Decommission requires the terminal to be Disabled or Maintenance (cannot decommission an Active terminal).
- Reassignment to another branch requires the terminal to be Disabled or Maintenance.
- Rename is allowed in any non-Decommissioned status.

#### Commands

| Command | Description |
|---------|-------------|
| `RegisterTerminal` | Register a new terminal for a branch |
| `ActivateTerminal` | Set terminal status to Active |
| `DisableTerminal` | Set terminal status to Disabled |
| `SetTerminalMaintenance` | Set terminal to Maintenance mode |
| `RenameTerminal` | Update the terminal's human-readable name |
| `ReassignTerminal` | Move terminal to a different branch (requires Disabled or Maintenance) |
| `DecommissionTerminal` | Permanently retire the terminal with a reason (requires Disabled or Maintenance) |
| `RecommissionTerminal` | Restore a decommissioned terminal to Disabled status with a reason |

#### Events

| Event | Description |
|-------|-------------|
| `TerminalRegistered` | Terminal was registered |
| `TerminalActivated` | Terminal was activated |
| `TerminalDisabled` | Terminal was disabled |
| `TerminalMaintenanceSet` | Terminal entered maintenance mode |
| `TerminalRenamed` | Terminal name was updated (carries oldName and newName) |
| `TerminalReassigned` | Terminal was moved to a different branch (carries oldBranchId and newBranchId) |
| `TerminalDecommissioned` | Terminal was permanently retired (carries reason) |
| `TerminalRecommissioned` | Decommissioned terminal was restored to Disabled status (carries reason) |

---

### 1.2 Shift (Aggregate Root)

Represents a cashier working session on a terminal. This is the **primary aggregate** for operational accountability.

#### Fields

| Field | Type | Description |
|-------|------|-------------|
| `shiftId` | `ShiftId` (VO) | Unique identifier |
| `terminalId` | `TerminalId` (VO) | Terminal this shift is on |
| `branchId` | `BranchId` (VO) | Branch context |
| `cashierId` | `CashierId` (VO) | Cashier operating this shift |
| `status` | `ShiftStatus` (Enum) | Open, Closed, ForcedClosed |
| `openedAt` | `DateTimeImmutable` | When shift was opened |
| `closedAt` | `?DateTimeImmutable` | When shift was closed (nullable) |
| `openingCashAmount` | `Money` (VO) | Cash in drawer at shift open |
| `declaredClosingCashAmount` | `?Money` (VO) | Cashier-declared cash at close (nullable until close) |
| `expectedCashAmount` | `Money` (VO) | System-calculated expected cash (derived) |
| `varianceAmount` | `Money` (VO) | Difference between declared and expected (derived) |
| `cashDrops` | `CashDrop[]` | List of cash drops during shift |

#### Derived Calculations

```
expectedCash =
    openingCash
  + totalCashPayments
  - totalCashRefunds
  - sum(cashDrops)

variance = declaredClosingCash - expectedCash
```

#### Invariants

---

## Source File
docs/domain-model.md
