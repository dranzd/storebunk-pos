# Library Feedback — Standards & Index

This directory contains all feedback, requests, and bug reports directed **outward** from `dranzd/storebunk-pos` to the upstream `dranzd/*` libraries it depends on. It is the single source of truth for tracking issues we have encountered in those libraries and the resolutions we need from them.

---

## Open Feedback

See **[open-feedback.md](open-feedback.md)** for the current checklist of all pending feedback items, ordered by urgency. Update that file when an item is resolved — not this one.

---

## Directory Structure

Feedback is grouped by the target library. Each group is a subdirectory named after the library. Each feedback file is prefixed with a 4-digit incremental number unique across the entire `library-feedback/` tree.

```
docs/library-feedback/
├── README.md                              ← this file (standards reference)
├── open-feedback.md                       ← living checklist of pending items
├── common-cqrs/                           ← dranzd/common-cqrs
├── common-event-sourcing/                 ← dranzd/common-event-sourcing
├── common-valueobject/                    ← dranzd/common-valueobject
├── common-domain-assert/                  ← dranzd/common-domain-assert
└── common-utils/                          ← dranzd/common-utils
```

Add a new subdirectory if feedback targets a library not listed above.

### Numbering

Numbers are assigned sequentially across the entire tree, starting at `0001`. The group folder does not dictate the number range — the next available number is used regardless of which library is targeted.

---

## Feedback File Naming

```
NNNN-short-kebab-case-title.md
```

- `NNNN` — 4-digit number, unique within the entire `library-feedback/` tree.
- Short title — lowercase kebab-case, describes the problem or need, not the solution.

**Examples:**
- `0001-command-bus-interface-missing-return-type.md`
- `0002-aggregate-event-accessor-convention.md`

---

## Feedback File Template

Every feedback file must follow this exact template. Do not omit any section.

```markdown
# NNNN — Short Human-Readable Title

**Target Library:** dranzd/<library-name>
**Type:** Bug | Missing Feature | API Change Request | Improvement
**Urgency:** Blocking | High | Medium | Low
**Status:** Open | Acknowledged | Resolved | Rejected
**Reported:** YYYY-MM-DD
**Resolved:** YYYY-MM-DD (leave blank if open)
**Affects Us:** list of files in storebunk-pos that are impacted

---

## What We Encountered

Clear description of the problem or gap as experienced from the consumer side. State what we tried to do, what happened, and why it is a problem for us. Do not assume the library author knows our domain context — be explicit.

---

## Expected Resolution

What we need from the library. Be specific:
- A new method / interface / class
- A renamed or changed signature
- A documented contract or behaviour guarantee
- A bug fix with expected correct behaviour

If multiple options would satisfy us, list them in order of preference.

---

## Workaround in Place

Describe any temporary workaround currently in use in `storebunk-pos` while waiting for the library to be updated. If no workaround exists, state "None — this is blocking."

---

## Library Response

> _(Filled in when the library maintainer responds)_

**Decision:** Accept | Reject | Defer | Needs Discussion
**Notes:**

_(Free-form space for the library maintainer's response, planned approach, or timeline.)_

---

## Resolution

_(Filled in when resolved)_

**Resolved:** YYYY-MM-DD
**Library Version:** the version that includes the fix
**Summary:** Brief description of what changed in the library and any follow-up changes needed in storebunk-pos.
```

---

## Urgency Values

| Urgency | Meaning |
|---------|---------|
| **Blocking** | We cannot implement a required feature without this; no viable workaround |
| **High** | Workaround exists but is fragile, misleading, or technically incorrect |
| **Medium** | Workaround is acceptable short-term; library change would improve correctness or clarity |
| **Low** | Nice-to-have; convention or style alignment |

## Status Values

| Status | Meaning |
|--------|---------|
| **Open** | Submitted or pending; no response yet |
| **Acknowledged** | Library maintainer is aware and has responded |
| **Resolved** | Library updated; storebunk-pos updated to use the fix |
| **Rejected** | Library maintainer declined; workaround remains permanent |

---

## Feedback Index

| ID | Library | Title | Type | Urgency | Status | Reported |
|----|---------|-------|------|---------|--------|----------|

_(Add rows here as feedback items are created.)_

---

**Last Updated:** 2026-02-19
