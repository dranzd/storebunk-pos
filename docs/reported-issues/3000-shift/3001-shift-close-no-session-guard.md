# 3001 — `CloseShift` dispatches unconditionally — no active session guard

**Type:** Missing Feature
**Status:** Open
**Severity:** High
**Reported:** 2026-02-19
**Resolved:**
**Affects:**
- `src/Domain/Model/Shift/Shift.php`
- `src/Application/Shift/Command/Handler/CloseShiftHandler.php`
- `src/Domain/Service/` (no policy service exists)

---

## Issue

There is no `ShiftCloseBlockPolicy` or equivalent guard to prevent closing a shift while active POS sessions exist. The spec requires shift close to block if unresolved Draft or Confirmed orders exist, but the `CloseShift` command dispatches unconditionally — `CloseShiftHandler` loads the `Shift` aggregate and calls `shift->close()` with no pre-condition check on active sessions. Suggest adding a `canClose(activeSessionIds: array): bool` method on the `Shift` aggregate or a dedicated policy service that the adapter can call before dispatching `CloseShift`.

---

## Findings

`CloseShiftHandler` at `src/Application/Shift/Command/Handler/CloseShiftHandler.php`:

```php
public function __invoke(CloseShift $command): void
{
    $shift = $this->shiftRepository->load($command->shiftId());
    $shift->close($command->declaredClosingCashAmount());
    $this->shiftRepository->store($shift);
}
```

`Shift::close()` at `src/Domain/Model/Shift/Shift.php:62` only checks that the shift is currently open:

```php
public function close(Money $declaredClosingCashAmount): void
{
    if (!$this->status->isOpen()) {
        throw InvariantViolationException::withMessage('Cannot close a shift that is not open');
    }
    // ... proceeds unconditionally
}
```

No check exists for:
- Active `PosSession` aggregates associated with this shift
- Unresolved Draft or Confirmed orders within those sessions

The `Shift` aggregate holds no reference to active sessions (correct by design — sessions are a separate aggregate), so any guard must be applied at the application layer using a read model query before the command is dispatched.

The `ForceCloseShiftHandler` has the same gap but is intentionally permissive by design (supervisor override), so only `CloseShiftHandler` is in scope.

---

## Root Cause

The invariant "shift cannot close with unresolved orders" is documented in the domain spec but was not implemented as an application-layer pre-condition check. The `Shift` aggregate correctly avoids coupling to `PosSession`, but no application service or policy was added to bridge the gap using a read model query before dispatch.

---

## Recommended Action

**Option A — Application-layer pre-condition in `CloseShiftHandler` (preferred):**

Add a read model query in `CloseShiftHandler` before dispatching to the aggregate:

```php
public function __invoke(CloseShift $command): void
{
    $activeSessions = $this->posSessionReadModel->findActiveByShiftId($command->shiftId());
    if (!empty($activeSessions)) {
        throw InvariantViolationException::withMessage(
            'Cannot close shift: active POS sessions exist'
        );
    }

    $shift = $this->shiftRepository->load($command->shiftId());
    $shift->close($command->declaredClosingCashAmount());
    $this->shiftRepository->store($shift);
}
```

**Option B — Dedicated `ShiftClosePolicy` domain service:**

Introduce `src/Domain/Service/ShiftClosePolicy.php` with a `canClose(ShiftId, array $activeSessionIds): void` method that throws on violation. The handler calls the policy before dispatching.

Option A is simpler and consistent with how `CloseShiftHandler` already has access to infrastructure. Option B is preferred if the policy logic is expected to grow (e.g., checking pending sync queue as well).

Files to change:
- `src/Application/Shift/Command/Handler/CloseShiftHandler.php` — add pre-condition check
- `src/Infrastructure/PosSession/ReadModel/` — ensure `findActiveByShiftId()` exists on the read model
- (Option B only) `src/Domain/Service/ShiftClosePolicy.php` — new policy service

---

## Owner Response

> _(Owner fills in this section before implementation begins)_

**Decision:**  Option B, we need the policy feature now as it will be used in other places as well
**Preferred Option:**  Option B
**Notes:**

---

## Resolution

_(Filled in when resolved)_

**Resolved:**
**Commit/PR:**
**Summary:**
