<!-- hash: 4913c458dee671201a5c4dab3ffba0bf3df4b0ff34f9d121598f29f915865e71 -->
# shift-business-rules

Category: ddd
Status: stable
Source: storebunk-pos

---

Shift aggregate must enforce cashier accountability and cash handling invariants. These rules ensure operational discipline and audit trail integrity for cash management.

1. One cashier = one terminal per open shift.
2. A cashier cannot open multiple shifts simultaneously.
3. A terminal cannot have multiple open shifts.
4. Shift cannot close if Draft orders exist for this shift.
5. Shift cannot close if Confirmed (not Completed) orders exist for this shift.
6. Force close requires supervisor authorization.
7. Force close does NOT auto-cancel Confirmed orders.
8. Cash drops cannot be edited or deleted once recorded.
9. No expense withdrawals allowed.

#### Commands

| Command | Description |
|---------|-------------|
| `OpenShift` | Open a new shift for a cashier on a terminal |
| `CloseShift` | Close the shift with declared cash amount |
| `ForceCloseShift` | Force close with supervisor auth (audit logged) |
| `RecordCashDrop` | Record a cash removal from the drawer |

#### Events

| Event | Description |
|-------|-------------|
| `ShiftOpened` | Shift was opened (includes openingCashAmount) |
| `ShiftClosed` | Shift was closed normally (includes variance) |
| `ShiftForceClosed` | Shift was force-closed by supervisor |
| `CashDropRecorded` | Cash was removed from the drawer |

#### Policies

- **ShiftCloseBlockPolicy**: Before closing, verify no unresolved orders exist.
- **CashVariancePolicy**: On close, compute and record variance. Never silently correct.

---

### 1.3 PosSession (Aggregate Root)

Represents the active UI lifecycle on a terminal during a shift. Manages which order is currently being worked on.

#### Fields

| Field | Type | Description |
|-------|------|-------------|
| `sessionId` | `SessionId` (VO) | Unique identifier |
| `shiftId` | `ShiftId` (VO) | Parent shift |
| `terminalId` | `TerminalId` (VO) | Terminal context |
| `activeOrderId` | `?OrderId` (VO) | Currently active order (nullable) |
| `parkedOrderIds` | `OrderId[]` | Orders parked for later |
| `inactiveOrderIds` | `OrderId[]` | Orders deactivated due to TTL expiry |
| `pendingSyncOrderIds` | `OrderId[]` | Offline orders awaiting sync |
| `state` | `SessionState` (Enum) | Idle, Building, Checkout |

#### Invariants

---

## Source File
docs/domain-model.md
