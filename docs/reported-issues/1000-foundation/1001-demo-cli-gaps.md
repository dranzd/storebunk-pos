# 1001 — Demo CLI Gaps: Unregistered Commands, Silent Arg Misparse, Hardcoded Data Path

**Type:** Improvement
**Status:** Open
**Severity:** Medium
**Reported:** 2026-08-17
**Resolved:**
**Affects:** demo/bootstrap.php, demo/demo, demo/cli/CliArgs.php, demo/scenarios/03-park-and-resume.sh, demo/scenarios/04-draft-ttl-expiry.sh, demo/scenarios/06-offline-sync.sh

---

## Issue

Three demo-tooling gaps, bundled as one issue because they share a home (the demo CLI) and none touches library code:

1. Four Terminal commands have working handlers but are not reachable from the demo.
2. `CliArgs` silently misparses the space-separated option form, producing wrong values instead of an error.
3. Three scenario scripts hardcode the state-file path, defeating the `POS_DEMO_DATA_DIR` isolation the stores honor.

---

## Findings

1. **Unregistered commands** — `DecommissionTerminal`, `ReassignTerminal`, `RecommissionTerminal`, `RenameTerminal` have handlers in `src/Application/Terminal/Command/Handler/`, but grep of `demo/bootstrap.php` and `demo/demo` finds no registration and no subcommand for any of them. Dead in the demo since they were added.
2. **Silent arg misparse** — `demo/cli/CliArgs.php:20-24` only handles `--flag=value`; a bare `--flag` becomes boolean `true` and the following token falls into positionals. So `./demo/demo terminal register --name SpacedName` "succeeds" with the wrong name (observed: a terminal named `1`). Silent-wrong, not an error.
3. **Hardcoded data path** — `demo/scenarios/04-draft-ttl-expiry.sh:29`, `06-offline-sync.sh:32,37`, `03-park-and-resume.sh:39,50` read `demo/data/demo-state.json` via a literal path. `FileEventStore`/`StateStore` honor `POS_DEMO_DATA_DIR` (added 2026-08-16 for test isolation), so these scenarios cannot run against a scratch dir — a CI-isolation gap.

All verified on `main` at `eb77e16` during the 2026-08-16 finalize-local thorough review (round 2); all pre-existing.

---

## Root Cause

The demo CLI grew feature-by-feature alongside the library without a completeness check against the command catalog, and `CliArgs`/the scenario scripts were written before the `POS_DEMO_DATA_DIR` override existed — nothing re-audited them when the convention changed.

---

## Recommended Action

1. Register the four handlers in `demo/bootstrap.php` and add the matching `terminal decommission|reassign|recommission|rename` subcommands (mirroring the existing terminal subcommand shape), plus a scenario or smoke assertion touching each.
2. In `CliArgs`, either support the space-separated form (`--name SpacedName`) or reject it loudly — never silently misparse. Loud rejection is the smaller change and keeps one canonical form.
3. Resolve the state-file path in scenarios 03/04/06 from `POS_DEMO_DATA_DIR` (falling back to `demo/data`) instead of the literal path.

Demo-only change; no `src/` modification involved.

---

## Owner Response

> _(Owner fills in this section before implementation begins)_

**Decision:** Accept | Reject | Defer | Needs Discussion
**Preferred Option:**
**Notes:**

---

## Resolution

_(Filled in when resolved)_

**Resolved:**
**Commit/PR:**
**Summary:**
