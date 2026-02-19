# Reported Issues — Standards & Index

This directory contains all reported issues, improvements, and architectural concerns for the `dranzd/storebunk-pos` library. It is the single source of truth for tracking problems discovered during development, integration, or review.

---

## Open Issues

See **[open-issues.md](open-issues.md)** for the current checklist of all unresolved issues, ordered by severity. Update that file when issues are resolved — not this one.

---

## Directory Structure

Issues are grouped by domain area, mirroring the feature series numbering used in `docs/features/README.md`. Each group is a subdirectory. Each issue is a single Markdown file prefixed with a 4-digit incremental number.

```
docs/reported-issues/
├── README.md                          ← this file (standards reference)
├── 6000-bc-integration/               ← External BC ports (Ordering, Inventory, Payment)
│   ├── 6001-convert-soft-reservation-to-hard.md
│   └── 6002-deduct-inventory-mapping.md
├── 8000-concurrency/                  ← Multi-terminal, idempotency, versioning
│   └── 8001-multi-terminal-enforcement-in-memory.md
└── 9000-offline-sync/                 ← Offline draft creation and sync
    ├── 9001-order-created-offline-accessor-naming.md
    └── 9002-deactivate-order-command-missing.md
```

### Group Series

| Series | Group Name | Matches Feature Series |
|--------|-----------|----------------------|
| 1000 | Foundation / Shared Kernel | 1000 |
| 2000 | Terminal Aggregate | 2000 |
| 3000 | Shift Aggregate | 3000 |
| 4000 | PosSession Aggregate | 4000 |
| 5000 | Checkout and Payment | 5000 |
| 6000 | External BC Integration | 6000 |
| 7000 | Draft Lifecycle | 7000 |
| 8000 | Multi-Terminal and Concurrency | 8000 |
| 9000 | Offline and Sync | 9000 |

If an issue spans multiple groups, place it in the group of the **primary affected component**.

---

## Issue File Naming

```
NNNN-short-kebab-case-title.md
```

- `NNNN` — 4-digit number, unique within the entire `reported-issues/` tree (not just within the group folder). Start from the group series number (e.g., first issue in 6000 series = `6001`).
- Short title — lowercase kebab-case, describes the problem, not the solution.

**Examples:**
- `6001-convert-soft-reservation-to-hard.md`
- `8001-multi-terminal-enforcement-in-memory.md`

---

## Issue File Template

Every issue file must follow this exact template. Do not omit any section.

```markdown
# NNNN — Short Human-Readable Title

**Type:** Bug | Improvement | Missing Feature | Architecture
**Status:** Open | In Review | Resolved | Rejected
**Severity:** Critical | High | Medium | Low
**Reported:** YYYY-MM-DD
**Resolved:** YYYY-MM-DD (leave blank if open)
**Affects:** list of affected files or components

---

## Issue

Clear description of the problem as reported or observed. State what is wrong or missing, not the solution.

---

## Findings

Detailed investigation results. Include:
- Exact file paths and line references where the problem exists
- Code snippets where helpful
- Confirmation of whether the issue is valid, invalid, or broader than reported
- Any related components discovered during investigation

---

## Root Cause

Single concise paragraph explaining **why** the problem exists. Focus on the underlying design decision or gap, not the symptom.

---

## Recommended Action

Specific, actionable recommendation(s). May include options (Option A / Option B) with a preferred option stated. List all files that would need to change.

---

## Owner Response

> _(Owner fills in this section before implementation begins)_

**Decision:** Accept | Reject | Defer | Needs Discussion
**Preferred Option:** _(if multiple options were given)_
**Notes:**

_(Free-form space for the owner to record their preferred approach, constraints, or additional context before work begins.)_

---

## Resolution

_(Filled in when resolved)_

**Resolved:** YYYY-MM-DD
**Commit/PR:** link or reference
**Summary:** Brief description of what was done.
```

---

## Status Values

| Status | Meaning |
|--------|---------|
| **Open** | Reported, not yet reviewed or acted on |
| **In Review** | Under active investigation |
| **Resolved** | Fix implemented and verified |
| **Rejected** | Determined not to be a valid issue or not worth fixing |

## Severity Values

| Severity | Meaning |
|----------|---------|
| **Critical** | Broken in production; invariants unenforced; data loss risk |
| **High** | Feature incomplete or incorrect; blocks integration |
| **Medium** | Naming/API inconsistency; adapter is no-op; misleading design |
| **Low** | Style, convention, or minor clarity issue |

---

## Issue Index

| ID | Group | Title | Type | Severity | Status | Reported |
|----|-------|-------|------|----------|--------|----------|
| [6001](6000-bc-integration/6001-convert-soft-reservation-to-hard.md) | BC Integration | `convertSoftReservationToHard()` has no inventory BC mapping | Improvement | Medium | Open | 2026-02-19 |
| [6002](6000-bc-integration/6002-deduct-inventory-mapping.md) | BC Integration | `deductInventory()` name misleading, mapping unclear | Improvement | Medium | Open | 2026-02-19 |
| [8001](8000-concurrency/8001-multi-terminal-enforcement-in-memory.md) | Concurrency | `MultiTerminalEnforcementService` uses in-memory state | Architecture | Critical | Open | 2026-02-19 |
| [9001](9000-offline-sync/9001-order-created-offline-accessor-naming.md) | Offline/Sync | Offline events use `get`-prefixed accessors, inconsistent convention | Improvement | Low | Open | 2026-02-19 |
| [9002](9000-offline-sync/9002-deactivate-order-command-missing.md) | Offline/Sync | `DeactivateOrder` CQRS command and handler are missing | Missing Feature | High | Open | 2026-02-19 |

---

**Last Updated:** 2026-02-19
