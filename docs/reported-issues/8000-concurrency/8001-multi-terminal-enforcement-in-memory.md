# 8001 — `MultiTerminalEnforcementService` uses in-memory state, cannot enforce across requests

**Type:** Architecture  
**Status:** Open  
**Severity:** Critical  
**Reported:** 2026-02-19  
**Resolved:**  
**Affects:**
- `src/Domain/Service/MultiTerminalEnforcementService.php`
- `tests/Unit/Domain/Service/MultiTerminalEnforcementServiceTest.php`
- Any handler calling `registerOpenShift()`, `unregisterOpenShift()`, `bindOrderToTerminal()`, `unbindOrder()`

---

## Issue

`MultiTerminalEnforcementService` is an in-memory domain service (state stored in PHP arrays). It cannot enforce invariants across HTTP requests or multiple processes. The adapter layer must re-implement enforcement via read model queries (`pos_shifts`, `pos_sessions`) on every request. Suggest replacing with a stateless service that accepts read model data as arguments, or exposing query methods that the adapter can call with persisted state.

---

## Findings

`MultiTerminalEnforcementService` at `src/Domain/Service/MultiTerminalEnforcementService.php` stores all state in three PHP arrays:

```php
/** @var array<string, string> terminalId => shiftId */
private array $openShiftsByTerminal = [];

/** @var array<string, string> cashierId => terminalId */
private array $activeTerminalByCashier = [];

/** @var array<string, string> orderId => terminalId */
private array $orderTerminalBinding = [];
```

Every HTTP request creates a fresh service instance — all state is lost between requests. As a result:

- `assertTerminalHasNoOpenShift()` will always pass on a fresh instance (array is empty)
- `assertCashierHasNoOpenShift()` will always pass on a fresh instance
- `assertOrderBelongsToTerminal()` will always pass on a fresh instance (no binding recorded)

The invariants **One cashier = one terminal per open shift** and **One terminal = one open shift** are completely unenforced in any real deployment.

The mutation methods (`registerOpenShift`, `unregisterOpenShift`, `bindOrderToTerminal`, `unbindOrder`) only affect the in-memory state of the current request and have no persistence effect.

`ShiftReadModelInterface` at `src/Application/Shift/ReadModel/ShiftReadModelInterface.php` already exposes `getOpenShifts()` and `getShiftsByTerminal()` — the read model data needed to enforce these invariants **already exists** in the application layer. The service just never reads from it.

---

## Root Cause

The service was designed for unit-testability (in-memory state is easy to set up in tests) but was never designed to be stateless. It was implemented as a domain service that owns state, when it should be a pure invariant checker that receives state as input. In a real adapter, the consumer would need to pre-populate the service from the read model before calling assert methods — but there is no mechanism for this and it is not documented.

---

## Recommended Action

**Option A (preferred) — Stateless service with injected data:**  
Change the assert methods to accept current state as arguments rather than reading from internal arrays:

```php
public function assertTerminalHasNoOpenShift(TerminalId $terminalId, array $openShiftsByTerminal): void;
public function assertCashierHasNoOpenShift(string $cashierId, array $activeTerminalByCashier): void;
public function assertOrderBelongsToTerminal(OrderId $orderId, TerminalId $terminalId, array $orderTerminalBinding): void;
```

The adapter layer queries the read model and passes the data in. The service becomes a pure invariant checker with no mutable state. The mutation methods (`registerOpenShift`, etc.) are removed — state is owned by the read model projections.

**Option B — Extract an interface, provide two implementations:**  
Define `MultiTerminalEnforcementServiceInterface` as a port. Keep the in-memory implementation for tests. Add a read-model-backed implementation for production that queries `ShiftReadModelInterface` and a session read model on each call.

**Option C — Move enforcement into command handlers:**  
Remove the domain service entirely. Move enforcement logic directly into `OpenShiftHandler` and `StartNewOrderHandler` using `ShiftReadModelInterface`. Simpler but loses the centralized domain expression of the invariant.

**Recommended: Option A.** It preserves the domain service concept, keeps invariant logic centralized and testable, and requires minimal structural change. Tests must be updated to pass state arrays as arguments instead of calling register/bind methods.

Files to change:
- `src/Domain/Service/MultiTerminalEnforcementService.php` — redesign assert methods to be stateless
- `tests/Unit/Domain/Service/MultiTerminalEnforcementServiceTest.php` — update to new signature
- All handlers that currently call `registerOpenShift()`, `unregisterOpenShift()`, `bindOrderToTerminal()`, `unbindOrder()` — remove those calls; read model is the source of truth

---

## Owner Response

> _(Owner fills in this section before implementation begins)_

**Decision:**  
**Preferred Option:**  
**Notes:**

---

## Resolution

_(Filled in when resolved)_

**Resolved:**  
**Commit/PR:**  
**Summary:**
