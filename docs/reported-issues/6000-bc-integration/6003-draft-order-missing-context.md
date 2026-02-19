# 6003 — `createDraftOrder()` accepts no customer or branch context

**Type:** Design Gap
**Status:** Resolved
**Severity:** High
**Reported:** 2026-02-19
**Resolved:** 2026-02-19
**Affects:**
- `src/Domain/Service/OrderingServiceInterface.php`
- `src/Application/PosSession/Command/Handler/AddOrderLineHandler.php`

---

## Issue

`OrderingServiceInterface::createDraftOrder(OrderId $orderId)` accepts only an `OrderId`. There is no way to pass a customer reference, branch context, or walk-in customer flag. The adapter must fabricate a random UUID as `customerId`, which breaks the SalesOrder BC's customer association. Suggest adding optional `customerId` and `branchId` parameters, or a `DraftOrderContext` DTO, so the POS can create properly attributed draft orders.

---

## Findings

The interface is defined at `src/Domain/Service/OrderingServiceInterface.php:11`:

```php
public function createDraftOrder(OrderId $orderId): void;
```

The POS already has `BranchId` available at session open time (it is carried on the `Shift` aggregate and flows into `PosSession` via `OpenPosSession`). A `customerId` may be known (loyalty card scan, staff-assigned customer) or absent (anonymous walk-in).

The SalesOrder BC's `CreateDraftOrder` command in `storebunk-ordering` requires at minimum a `customerId` and `branchId` to create a valid `SalesOrder` aggregate. Without them the adapter must either:

1. Fabricate a sentinel UUID for `customerId` (breaks customer association and reporting), or
2. Throw at adapter construction time (blocks all POS usage until the interface is fixed).

Neither is acceptable in production. The current `StubOrderingService` silently ignores the gap because it does not validate these fields.

---

## Root Cause

The interface was designed from the POS perspective only — the `OrderId` is the POS-generated correlation key. The context required by the downstream SalesOrder BC (`customerId`, `branchId`) was not included in the port contract, leaving the adapter unable to construct a valid command without fabricating data.

---

## Recommended Action

**Option A — Add parameters directly (preferred for simplicity):**

```php
public function createDraftOrder(
    OrderId $orderId,
    string $branchId,
    ?string $customerId = null
): void;
```

`$customerId = null` signals a walk-in / anonymous customer; the adapter maps this to whatever sentinel the SalesOrder BC uses for anonymous orders.

**Option B — Introduce a `DraftOrderContext` DTO:**

```php
final class DraftOrderContext
{
    public function __construct(
        public readonly string $branchId,
        public readonly ?string $customerId = null,
    ) {}
}

public function createDraftOrder(OrderId $orderId, DraftOrderContext $context): void;
```

Option B is more extensible if additional context fields are anticipated (e.g., `salesChannelId`, `priceListId`).

Files to change:
- `src/Domain/Service/OrderingServiceInterface.php` — add parameters or DTO
- `src/Application/PosSession/Command/Handler/AddOrderLineHandler.php` — pass context at call site
- `tests/Stub/Service/StubOrderingService.php` — update stub signature

---

## Owner Response

> _(Owner fills in this section before implementation begins)_

**Decision:**
**Preferred Option:**  Option B
**Notes:**

---

## Resolution

**Resolved:** 2026-02-19
**Commit/PR:** fix/6003-draft-order-context
**Summary:** Implemented Option B. Created `DraftOrderContext` DTO in `src/Domain/Service/` with `branchId` (required) and `customerId` (optional, null = walk-in). Updated `OrderingServiceInterface::createDraftOrder()` to accept the DTO as a second parameter. Added `branchId` and `customerId` to `SyncOrderOnline` command. Updated `SyncOrderOnlineHandler` to build `DraftOrderContext` from command fields. Updated `StubOrderingService` and all integration test call sites. All 121 tests pass.
