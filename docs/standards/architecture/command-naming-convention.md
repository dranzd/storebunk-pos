<!-- hash: 0180fef1e20180ffcd6eee7952f79621c01db161bc19a063e0aec331fa60c339 -->
# command-naming-convention

Category: architecture
Status: stable
Source: storebunk-pos

---

Commands must use {ActionEntity}.php naming without Command suffix. Commands represent intentions to change state and should be simple data structures.

### Files
- **Aggregates**: `{Name}.php` (e.g., `Shift.php`, `Terminal.php`)
- **Value Objects**: `{Name}.php` (e.g., `ShiftId.php`, `CashDrop.php`)
- **Enums**: `{Name}.php` (e.g., `ShiftStatus.php`, `SessionState.php`)
- **Events**: `{ActionPastTense}.php` (e.g., `ShiftOpened.php`, `CashDropRecorded.php`)
- **Commands**: `{ActionEntity}.php` — no `Command` suffix (e.g., `OpenShift.php`, `StartSession.php`)
- **Handlers**: `{ActionEntity}Handler.php` (e.g., `OpenShiftHandler.php`, `StartSessionHandler.php`)
- **Interfaces**: `{Name}Interface.php` (e.g., `ShiftRepositoryInterface.php`, `TerminalReadModelInterface.php`)
- **Read Model Implementations**: `InMemory{Name}ReadModel.php` (e.g., `InMemoryTerminalReadModel.php`)
- **Repository Implementations**: `InMemory{Name}Repository.php` (e.g., `InMemoryTerminalRepository.php`)
- **Stubs**: `Stub{Name}.php` in `tests/Stub/` (e.g., `StubOrderingService.php`)

---

## Source File
docs/folder-structure.md
