# Command Refactoring Status

## Completed

### 1. All 25 Commands Refactored ✓
**Terminal Commands (8):**
- `RegisterTerminal::register($terminalId, $branchId, $name)`
- `ActivateTerminal::withId($terminalId)`
- `DisableTerminal::withId($terminalId)`
- `DecommissionTerminal::because($terminalId, $reason)`
- `ReassignTerminal::toBranch($terminalId, $newBranchId)`
- `RenameTerminal::to($terminalId, $newName)`
- `SetTerminalMaintenance::forTerminal($terminalId)`
- `RecommissionTerminal::because($terminalId, $reason)`

**Shift Commands (4):**
- `OpenShift::forCashier($shiftId, $terminalId, $branchId, $cashierId, $amount, $currency)`
- `CloseShift::withCashAmount($shiftId, $amount, $currency)`
- `RecordCashDrop::ofAmount($shiftId, $amount, $currency)`
- `ForceCloseShift::bySupervisor($shiftId, $supervisorId, $reason)`

**PosSession Commands (13):**
- `StartSession::onTerminal($sessionId, $shiftId, $terminalId)`
- `StartNewOrder::withOrder($sessionId, $orderId)`
- `CancelOrder::because($sessionId, $reason)`
- `RequestPayment::via($sessionId, $amount, $currency, $paymentMethod)`
- `InitiateCheckout::forSession($sessionId)`
- `CompleteOrder::forSession($sessionId)`
- `ParkOrder::forSession($sessionId)`
- `ResumeOrder::withOrder($sessionId, $orderId)`
- `DeactivateOrder::because($sessionId, $reason)`
- `ReactivateOrder::withOrder($sessionId, $orderId)`
- `EndSession::withId($sessionId)`
- `SyncOrderOnline::forOrder($sessionId, $orderId, $branchId, $customerId)`
- `StartNewOrderOffline::withOrder($sessionId, $orderId)`

### 2. Documentation ✓
- Created `docs/adr/002-command-primitive-parameters.md`

### 3. Demo/CLI Updated ✓
- `demo/cli/services/shift.php` - All 4 shift commands updated
- `demo/cli/services/session.php` - All 12 session commands updated
- Added missing `BranchId` import

### 4. Integration Tests Updated ✓
- `tests/Integration/DraftLifecycleIntegrationTest.php` - 4 commands updated
- `tests/Integration/OfflineSyncIntegrationTest.php` - 6 commands updated

### 5. Unit Tests Partially Updated
- `tests/Unit/Application/PosSession/Handler/DeactivateOrderHandlerTest.php` - 2/3 updated
- `tests/Unit/Application/Shift/Handler/CloseShiftHandlerTest.php` - 2/5 updated

## Remaining Work

### Unit Test Files Need Completion:
1. **DeactivateOrderHandlerTest.php**
   - Line 43: `new DeactivateOrder($sessionId, 'TTL expired')` → needs factory method
   - Line 93: `new StartSession($sessionId, $shiftId, $terminalId)` → needs factory method
   - Line 96: `new StartNewOrder($sessionId, $orderId)` → needs factory method

2. **CloseShiftHandlerTest.php**
   - Line 54: `new CloseShift(...)` → needs factory method
   - Line 105: `new CloseShift(...)` → needs factory method  
   - Line 127: `new CloseShift(...)` → needs factory method
   - Line 136: `new OpenShift(...)` → needs factory method
   - Line 155: `new StartSession(...)` → needs factory method

## Lint Errors (Can be ignored)

**Intelephense false positives:**
- `fromString()` undefined - These methods exist in common library value objects (Uuid base class)
- `fromScalar()` undefined - These methods exist in Money\Basic from common library

These are IDE linting issues, not actual code errors. The methods exist at runtime.

## Breaking Changes

All command instantiations must change from:
```php
new CommandName($valueObject1, $valueObject2)
```

To:
```php
CommandName::factoryMethod($primitive1, $primitive2)
```

## Next Steps

1. Complete remaining unit test updates
2. Run tests to verify everything works
3. Update any other code that instantiates commands directly
