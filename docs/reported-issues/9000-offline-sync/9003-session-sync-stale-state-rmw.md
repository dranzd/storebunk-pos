# 9003 — Demo sessionSync Rebuilds pending_sync_order_ids From a Stale Snapshot

**Type:** Bug
**Status:** Resolved
**Severity:** Medium
**Reported:** 2026-08-17
**Resolved:** 2026-08-17
**Affects:** demo/cli/services/session.php

---

## Issue

The demo CLI's `session sync` command removes the synced order from the `pending_sync_order_ids` list using a read-modify-write on state loaded at process start. A concurrent demo process that pushes a new pending order between this process's construction-time load and its `set()` gets its push silently clobbered.

---

## Findings

- `demo/cli/services/session.php:534-536` — `sessionSync()` calls `$stateStore->getList('pending_sync_order_ids')` (served from the in-memory snapshot loaded when the `StateStore` was constructed), filters out the synced order id, then `set()`s the **whole rebuilt list**.
- `StateStore::set()`/`mutate()` (hardened 2026-08-16) re-reads disk under the sidecar lock and merges *keys*, so two writers to *different* keys no longer lose each other — but a whole-list overwrite of the *same* key is applied verbatim: the merge cannot recover entries the writer never saw.
- Concrete interleaving: process A constructs (list = `[o1]`) → process B does `push('pending_sync_order_ids', 'o2')` (disk = `[o1, o2]`) → process A syncs `o1` and sets `[]` → `o2` is lost from the pending list even though it was never synced.
- The push side (`demo/cli/services/session.php:489`) uses `StateStore::push()`, which reads-current-under-lock, so the defect is one-directional: only the sync-side whole-list rewrite is stale.
- Demo-only surface: the library's real sync path (`PendingSyncQueue`) is unaffected.
- Verified on `main` at `eb77e16` during the 2026-08-16 finalize-local thorough review (round 2).

---

## Root Cause

The demo state hardening moved atomicity into `StateStore` (locked re-read + key-level merge on write), but `sessionSync` predates it and still performs its own read-modify-write at the call site — the one pattern key-level merging cannot make safe, because the unit of contention is entries *inside* one key's list value.

---

## Recommended Action

Give `StateStore` a locked list-removal primitive (e.g. `removeFromList(string $key, string $value)` implemented via `mutate()` so the filter runs against the *current* disk state under the lock), and use it in `sessionSync()` in place of the getList/filter/set sequence. Add a unit test mirroring `test_concurrent_writers_do_not_lose_each_others_keys` but for concurrent push + remove on the same list key.

Files: `demo/cli/StateStore.php`, `demo/cli/services/session.php`, `tests/Unit/Demo/StateStoreTest.php`.

---

## Owner Response

**Decision:** Accept
**Date answered:** 2026-08-17
**Notes:** Owner batch-approved implementation of all open triaged issues ("do all so we can be done with this").

---

## Resolution

**Resolved:** 2026-08-17
**Commit/PR:** branch `fix/8002-multi-terminal-enforcement-never-wired`
**Summary:** Added `StateStore::removeFromList()` — a locked list-removal primitive that filters the CURRENT on-disk list via `mutate()` — and switched `sessionSync()` to it, replacing the stale getList/filter/set sequence. Regression test `test_a_concurrent_push_survives_a_list_removal` proves an interleaved push from a second instance survives the removal.
