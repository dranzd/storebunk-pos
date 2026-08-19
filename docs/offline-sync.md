# Offline Sync — Technical Reference

This document describes the offline order creation and synchronization feature of StoreBunk POS. It covers the architecture, command flow, idempotency model, and integration guidance for consumers.

---

## 1. Overview

When network connectivity to the Ordering BC is unavailable, POS supports **offline draft order creation**. Orders created offline are queued locally and synchronized to the Ordering BC when connectivity is restored.

### Key Capabilities

- **Offline draft creation** — cashier can start new orders without network access
- **Pending sync queue** — offline orders are tracked until successfully synced
- **Idempotent replay** — a command carrying its original id (`withMessageUuid()`) can be safely retried without duplicate side effects. A redelivered CREATE — the same command id arriving twice — is absorbed rather than refused, and re-queues the order if it never synced, the same way a redelivered SYNC of an already-synced order re-issues its draft-order call. A DIFFERENT command carrying an order id the session has already used is refused: the command id is what tells a repeat apart from a reuse
- **Consumer-controlled command IDs** — callers may supply their own command ID for idempotency, or omit it to auto-generate one

### Limitations

- **Card payments** are not supported offline
- **External payment authorization** is not available offline
- **Inventory reservation** does not occur until sync (soft reservation is deferred)

---

## 2. Architecture

### Components

| Component | Location | Role |
|-----------|----------|------|
| `StartNewOrderOffline` | `Application\PosSession\Command\` | Command: create an order offline |
| `SyncOrderOnline` | `Application\PosSession\Command\` | Command: sync an offline order to Ordering BC |
| `StartNewOrderOfflineHandler` | `Application\PosSession\Command\Handler\` | Handler: processes offline order creation |
| `SyncOrderOnlineHandler` | `Application\PosSession\Command\Handler\` | Handler: processes online sync |
| `PendingSyncQueue` | `Domain\Service\` | Domain service: tracks orders awaiting sync |
| `IdempotencyRegistry` | `Application\Shared\` | Application service: prevents duplicate command processing |
| `OrderingServiceInterface` | `Domain\Service\` | Port: creates draft order in Ordering BC on sync |

### Events

| Event | Recorded By | Description |
|-------|-------------|-------------|
| `OrderCreatedOffline` | `PosSession::startNewOrderOffline()` | Draft order created while offline. Carries `commandId` for traceability. |
| `OrderMarkedPendingSync` | `PosSession::markOrderPendingSync()` | Order added to the pending sync list on the aggregate. |
| `OrderSyncedOnline` | `PosSession::syncOrderOnline()` | Order successfully synced. Removed from `pendingSyncOrderIds`. |

---

## 3. Command Flow

### 3.1 Offline Order Creation

```
Consumer
  │
  ▼
new StartNewOrderOffline($sessionId, $orderId)
  │
  ▼
StartNewOrderOfflineHandler::__invoke()
  │
  ├─ 1. Check IdempotencyRegistry → if this command id was already
  │      processed, return (no-op)
  ├─ 2. Load PosSession aggregate from repository
  ├─ 3. If THIS command already created this order (a redelivery reaching
  │      here because the registry was rebuilt without command ids, or the
  │      process died between storing the order and queueing it):
  │      ├─ re-queue it when it has not synced — otherwise nothing syncs it
  │      ├─ mark the command processed
  │      └─ return
  ├─ 4. session->startNewOrderOffline($orderId, $commandId)
  │      ├─ Refuses an order id this session has already used — by ANY
  │      │  command. Step 3 is what lets a redelivery past this; anything
  │      │  reaching here with a used id is a reuse
  │      └─ Records OrderCreatedOffline event (which persists the command id)
  ├─ 5. session->markOrderPendingSync($orderId)
  │      └─ Records OrderMarkedPendingSync event
  ├─ 6. Store session (persists events to event store)
  ├─ 7. pendingSyncQueue->enqueue($sessionId, $orderId, $commandId)
  └─ 8. idempotencyRegistry->markAsProcessed($commandId)

(An order still pending sync needs no separate guard: a queue entry only
exists once the create was stored, so step 3 already recognises it.)
```

**Aggregate state after offline creation:**
- `activeOrderId` is set to the new order ID
- `state` transitions to `Building`
- `pendingSyncOrderIds` includes the new order ID
- After `markOrderPendingSync`, `activeOrderId` is cleared and `state` returns to `Idle`

### 3.2 Online Sync (Reconnection)

```
Consumer (on reconnect)
  │
  ▼
new SyncOrderOnline($sessionId, $orderId, $context = [])
  │
  ▼
SyncOrderOnlineHandler::__invoke()
  │
  ├─ 1. Check IdempotencyRegistry → if already processed, return (no-op)
  ├─ 2. Load PosSession aggregate from repository
  ├─ 3. session->syncOrderOnline($orderId)
  │      └─ Records OrderSyncedOnline event
  │      └─ Removes orderId from pendingSyncOrderIds
  ├─ 4. Store session (persists events to event store)
  ├─ 5. orderingService->createDraftOrder($orderId, $command->context)
  │      └─ Calls Ordering BC to create the actual draft; the context is an
  │         opaque consumer-owned array POS never reads (ADR-006)
  ├─ 6. pendingSyncQueue->dequeueByOrderId($orderId)
  └─ 7. idempotencyRegistry->markAsProcessed($commandId)
```

**Aggregate state after sync:**
- `pendingSyncOrderIds` no longer contains the synced order ID
- The order now exists in the Ordering BC as a draft

---

## 4. Idempotency Model

Idempotency ensures that replaying the same command (e.g., due to network retry) does not produce duplicate side effects.

### How It Works

1. Every command has a **unique `messageUuid`** (the command ID), auto-generated by `GenericMessage::init()` as a UUID v4.
2. The consumer may **optionally provide their own command ID** via the base-class `withMessageUuid()` method. This is useful when the consumer needs deterministic idempotency keys (e.g., for client-side retry tracking).
3. Handlers check `IdempotencyRegistry::hasBeenProcessed($commandId)` before executing. If the command was already processed, the handler returns immediately.
4. After successful processing, the handler calls `IdempotencyRegistry::markAsProcessed($commandId)`.

### Command ID Generation

```php
// Auto-generated UUID v4 (default — each construction creates a unique ID)
$cmd = new StartNewOrderOffline($sessionId, $orderId);

// Consumer-provided ID (for deterministic idempotency) — withMessageUuid()
// returns a clone; use the return value
$cmd = (new StartNewOrderOffline($sessionId, $orderId))->withMessageUuid('my-idempotency-key');
```

All command classes follow this pattern (ADR-003): construction auto-generates a UUID v4 via the base class (`GenericMessage`); a deterministic id is applied afterwards with `withMessageUuid()`.

### Important: Command ID ≠ Aggregate ID

The command ID (`messageUuid`) must be **unique per command instance**. It must **never** be the aggregate ID (e.g., session ID, shift ID). Using the aggregate ID as the command ID would cause all commands targeting the same aggregate to collide in the `IdempotencyRegistry`, preventing subsequent commands from executing.

### Guards in Offline Creation

`StartNewOrderOfflineHandler` has two, and they answer different questions:

1. **`IdempotencyRegistry`** — has this exact command id already done THIS
   work? The registry records each id with what it did (message name plus
   target order), so a redelivery is absorbed while the same id claiming
   different work — the "one key per order" scheme, where a create and a sync
   share a key — is refused rather than swallowed.
2. **`PosSession::wasStartedByCommand()`** — did this exact command already
   create this order? This separates a REDELIVERY (absorbed, and re-queued if
   the order never synced) from a REUSE. `OrderCreatedOffline` persists the
   command id, which is what makes the distinction possible.

Anything else naming an order id the session has already used is refused by
the aggregate. That is why a retry must carry the original command id
(`withMessageUuid()`): a fresh command object gets a fresh id, and a fresh id
on a used order is a reuse by definition.

**The sync path makes the same distinction.** `OrderSyncedOnline` records the
command that synced the order, so `SyncOrderOnlineHandler` absorbs a
redelivery of THAT command (re-issuing the draft-order call, which is
idempotent per order id by contract) while an unrelated command naming an
already-synced order falls through to the pending-sync refusal. Events stored
before the command id was recorded answer "yes" for any command — they cannot
tell, and refusing them would break history that synced legitimately.

---

## 5. PendingSyncQueue

The `PendingSyncQueue` is a domain service that tracks offline orders awaiting synchronization.

### API

| Method | Description |
|--------|-------------|
| `enqueue(SessionId, OrderId, commandId)` | Add an order to the sync queue |
| `dequeueByOrderId(OrderId)` | Remove an order after successful sync |
| `hasByOrderId(OrderId): bool` | Check if an order is queued (see the note below) |
| `hasCommandId(string): bool` | Check if a command ID is in the queue |
| `all(): array` | Get all queued entries |
| `count(): int` | Number of queued orders |
| `isEmpty(): bool` | Whether the queue is empty |

### Queue Entry Structure

Each entry contains:
- `sessionId` — the POS session that created the order
- `orderId` — the order being tracked
- `commandId` — the original command ID (for traceability)
- `queuedAt` — timestamp when the order was queued

### Persistence Note

The current `PendingSyncQueue` is an **in-memory implementation**. Consumers integrating this library must provide a persistent implementation (e.g., database-backed) to survive process restarts. The in-memory version is suitable for testing and demo purposes.


**On `hasByOrderId` / `hasCommandId`:** neither is used by a handler. "Is this
order queued" cannot tell a redelivery from a different command reusing the
id, which is why offline creation asks the aggregate
`wasStartedByCommand()` instead. They remain for hosts inspecting the queue.

---

## 6. Consumer Integration Guide

### Wiring Dependencies

Both handlers require these collaborators:

```php
// StartNewOrderOfflineHandler
$handler = new StartNewOrderOfflineHandler(
    $posSessionRepository,   // PosSessionRepositoryInterface
    $pendingSyncQueue,       // PendingSyncQueue
    $idempotencyRegistry     // IdempotencyRegistry
);

// SyncOrderOnlineHandler
$handler = new SyncOrderOnlineHandler(
    $posSessionRepository,   // PosSessionRepositoryInterface
    $orderingService,        // OrderingServiceInterface (your adapter)
    $pendingSyncQueue,       // PendingSyncQueue
    $idempotencyRegistry     // IdempotencyRegistry
);
```

### Typical Reconnection Flow

```php
// 1. Detect connectivity restored

// 2. Iterate pending sync queue
foreach ($pendingSyncQueue->all() as $entry) {
    $command = new SyncOrderOnline(
        $entry['sessionId']->toNative(),
        $entry['orderId']->toNative(),
        // Opaque context — whatever YOUR Ordering BC integration needs.
        // POS forwards it verbatim and never reads the keys (ADR-006).
        // Recommended: build it via a consumer-owned translator so the key
        // literals live in one place, e.g.:
        YourDraftOrderContext::toArray(branchId: $branchId, customerId: $customerId)
    );
    $commandBus->dispatch($command);
}

// Your Ordering adapter is the other edge of the boundary: it receives the
// raw array and rehydrates the same translator —
// $ctx = YourDraftOrderContext::fromArray($context). See ADR-006.

// 3. Each successful sync removes the order from the queue
//    Failed syncs remain in the queue for retry
```

### Error Handling

- If `SyncOrderOnlineHandler` fails (e.g., Ordering BC unavailable), the order **remains** in the `PendingSyncQueue` and can be retried.
- The `IdempotencyRegistry` only marks a command as processed **after** successful completion, so a failed attempt will not block future retries.
- A failure can land **after** the `OrderSyncedOnline` event is durably stored but before `createDraftOrder()` succeeds. A retry of the same command heals that window: the aggregate is not mutated again, but the draft-order call is re-issued. Delivery to `OrderingServiceInterface::createDraftOrder()` is therefore **at-least-once** — the consumer's implementation MUST be idempotent per order id (see the interface docblock).
- Consumers should implement retry logic with appropriate backoff.

### Testing

Integration tests for the offline sync feature are in:
- `tests/Integration/OfflineSyncIntegrationTest.php`

These tests use `StubOrderingService` from `tests/Stub/Service/` and in-memory infrastructure.

---

## 7. Aggregate State Machine (Offline Path)

```
Session State:  Idle ──▶ Building ──▶ Idle (after markOrderPendingSync)
                                         │
                                         │  (on reconnect)
                                         ▼
                                    syncOrderOnline()
                                    removes from pendingSyncOrderIds
```

The offline path differs from the online path:

| Step | Online Path | Offline Path |
|------|-------------|--------------|
| Order creation | `startNewOrder()` → calls Ordering BC immediately | `startNewOrderOffline()` → no BC call, queued |
| Ordering BC call | Immediate | Deferred until `SyncOrderOnline` |
| Session state after creation | `Building` (order stays active) | `Idle` (order marked pending sync, no active order) |
| Inventory reservation | Immediate soft reservation | Deferred until sync |

---

## 8. Sequence Diagram

```
┌──────────┐    ┌──────────────┐    ┌──────────┐    ┌─────────────┐    ┌──────────────┐
│ Consumer  │    │OfflineHandler│    │PosSession│    │PendingSyncQ │    │IdempotencyReg│
└────┬─────┘    └──────┬───────┘    └────┬─────┘    └──────┬──────┘    └──────┬───────┘
     │                 │                  │                  │                  │
     │ StartNewOrderOffline               │                  │                  │
     │────────────────▶│                  │                  │                  │
     │                 │ hasBeenProcessed? │                  │                  │
     │                 │─────────────────────────────────────────────────────▶ │
     │                 │                  │                  │       no ◀──────│
     │                 │ wasStartedByCommand?                │                  │
     │                 │─────────────────▶│                  │                  │
     │                 │           no ◀───│                  │                  │
     │                 │ startNewOrderOffline                │                  │
     │                 │─────────────────▶│                  │                  │
     │                 │ markOrderPendingSync                │                  │
     │                 │─────────────────▶│                  │                  │
     │                 │ store            │                  │                  │
     │                 │─────────────────▶│                  │                  │
     │                 │ enqueue          │                  │                  │
     │                 │────────────────────────────────────▶│                  │
     │                 │ markAsProcessed  │                  │                  │
     │                 │──────────────────────────────────────────────────────▶│
     │                 │                  │                  │                  │

     ... (later, on reconnect) ...

     │ SyncOrderOnline │                  │                  │                  │
     │────────────────▶│                  │                  │                  │
     │                 │ (similar flow: check idempotency, sync, dequeue)      │
```
