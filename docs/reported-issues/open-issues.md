# Open Issues Checklist

All unresolved issues. Ordered by severity — most critical first.

When an issue is resolved, remove its line from this file and mark the issue file **Resolved**.

---

#### 🔴 Critical

_(none)_

#### 🟠 High

_(none)_

#### 🟡 Medium

- [ ] [9003](9000-offline-sync/9003-session-sync-stale-state-rmw.md) — Demo `session sync` rebuilds `pending_sync_order_ids` from a stale snapshot; concurrent pushes can be clobbered
- [ ] [2002](2000-terminal/2002-terminal-handler-test-coverage-gaps.md) — 13 application-layer handlers have no direct tests; plus event-store version round-trip assertion gap
- [ ] [1001](1000-foundation/1001-demo-cli-gaps.md) — Demo CLI gaps: 4 unregistered terminal commands, silent `--flag value` misparse, hardcoded state path in scenarios

#### 🔵 Low

_(none)_
