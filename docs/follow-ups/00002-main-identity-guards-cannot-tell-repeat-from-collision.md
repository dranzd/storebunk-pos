# 00002-main — Two guards still ask "is this id known?" instead of "did this command do it?"

**Raised:** 2026-08-19
**Source:** local multi-lens review, offline-sync path
**Status:** Open — both pre-date the shift-enforcement work; neither is introduced by it

The same defect shape was found and fixed three times on the offline-create path:
a guard keyed on an identity that cannot separate a legitimate repeat from a
collision will absorb the collision and report success for work it never did.
Two instances remain, both needing more than a local change.

## 1. IdempotencyRegistry knows the command id and nothing else

`IdempotencyRegistry` stores `array<string, true>` — a command id, with no record
of what that command did. Both offline handlers return early on it. A caller that
reuses one id across different commands (e.g. keying by order, which
`docs/offline-sync.md` §4 invites by encouraging deterministic keys) gets:

- CREATE marks `order-key` processed, SYNC carrying `order-key` returns immediately
- no draft order ever reaches the Ordering context
- the order stays in the pending queue forever
- the caller is told it succeeded

Reproduced in review. Arguably the caller violating the documented "unique per
command instance" contract — but the failure is silent, which is the part worth
fixing. Shape: record `{command type, target}` alongside the id and refuse a
mismatch instead of returning.

## 2. The sync path cannot make the distinction the create path now makes

`SyncOrderOnlineHandler`'s already-synced check is keyed on the order id alone,
because `OrderSyncedOnline` carries no command id — unlike `OrderCreatedOffline`,
which persists one precisely so the create path can tell a redelivery from a
reuse. An unrelated command naming a synced order is absorbed, re-issues the
draft-order call, dequeues, and is marked processed.

Safe today: the ordering port is idempotent per order id by contract. But it is a
weaker guarantee than the create path's, and the asymmetry is easy to miss.
Fixing it properly means adding a command id to `OrderSyncedOnline` — an event
schema change with upcasting implications, which is why it is filed rather than
folded in.
