# Documentation Process

This document defines how documentation is managed in the StoreBunk POS project, including the relationship between external standards and project-specific documentation.

---

## Overview

We maintain **two categories of documentation**:

| Category | Source | Purpose | Modification |
|----------|--------|---------|--------------|
| **Standards** | `dranzd/standards-doc` | Organization-wide coding standards, patterns, and conventions | **Read-only** — managed centrally |
| **Project Docs** | `docs/` in this repository | StoreBunk POS-specific architecture, decisions, and implementation details | **Editable** — maintained by the project team |

---

## Standards Documentation (External)

The `dranzd/standards-doc` package contains organization-wide standards that apply across all StoreBunk projects.

### Key Characteristics

- **Immutable in this project** — Standards are defined in the external library and imported via Composer
- **Version controlled** — Changes to standards go through the `dranzd/standards-doc` repository
- **Automatically available** — Located in `vendor/dranzd/standards-doc/` after `composer install`
- **Synced locally** — Copied to `docs/standards/` via the sync script for easy reference

### Available Standards

After syncing, standards are organized in `docs/standards/`:

```
docs/standards/
├── architecture/       # Hexagonal Architecture, CQRS, patterns
│   ├── command-naming-convention.md
│   ├── cqrs-separation.md
│   ├── hexagonal-architecture-principles.md
│   └── ...
├── ddd/                # Domain-Driven Design principles
│   ├── aggregate-invariant-enforcement.md
│   ├── pos-invariants.md
│   └── ...
└── event-sourcing/     # Event Sourcing patterns and naming
```

---

## Project Documentation (Local)

All project-specific documentation lives in the `docs/` directory.

### Document Categories

| Location | Content Type |
|----------|--------------|
| `docs/*.md` | Core project documentation (architecture, design, vision) |
| `docs/adr/` | Architectural Decision Records |
| `docs/features/` | Feature specifications and checklists |
| `docs/reported-issues/` | Issue tracking and resolution records |
| `docs/library-feedback/` | Feedback on common libraries |
| `docs/standards/` | **Read-only sync** from `dranzd/standards-doc` |

### Tagging Convention

When documenting patterns that should be shared as organization-wide standards, use these tags:

```markdown
<!-- @standard: Command Naming Convention -->
Content that follows an organization-wide standard
<!-- @end-standard -->

<!-- @best-practice: Error Handling -->
Content that represents project-specific best practices worth sharing
<!-- @end-best-practice -->
```

#### Tag Types

- `@standard` — Mandatory patterns and conventions for upstream sync
- `@best-practice` — Recommended approaches for upstream sync

#### Example

```markdown
## Command Naming

<!-- @standard: Command Naming Convention -->
Commands should be named using imperative verbs that describe the action:
- `RegisterTerminal` not `TerminalRegistration`
- `OpenShift` not `ShiftOpening`
- `StartSession` not `SessionStart`
<!-- @end-standard -->

<!-- @best-practice: Test Method Naming -->
Test methods should follow the pattern `it_{does_something}_{context}`
for readability in testdox output.
<!-- @end-best-practice -->
```

---

## Bidirectional Sync Workflow

The standards system supports **bidirectional synchronization**:

```
┌─────────────────────────────────────────────────────────────┐
│ StoreBunk POS                                               │
│ ┌─────────────────┐      ┌──────────────────┐             │
│ │ Your Docs       │      │ docs/standards/  │             │
│ │ (editable)      │      │ (read-only sync) │             │
│ │                 │      │                  │             │
│ │ @standard tags  │      │ Synced from lib  │             │
│ └────────┬────────┘      └────────▲─────────┘             │
│          │                        │                        │
│          │ standards-cli          │ vendor/bin/standards   │
│          │ (upstream)               │ (downstream)           │
└──────────┼────────────────────────┼────────────────────────┘
           │                        │
           ▼                        │
    ┌──────────────────────────────┴─────┐
    │ dranzd/standards-doc               │
    │ (central repository)               │
    └────────────────────────────────────┘
```

### Downstream Sync (Standards → Project)

**When to run:** After `composer update dranzd/standards-doc`

```bash
# Check what changed (diff only)
make standards-diff

# Preview sync (dry run)
make standards-dry-run

# Sync standards to docs/standards/
make standards-sync-down

# Or use vendor/bin/standards directly
vendor/bin/standards --diff-only
vendor/bin/standards --dry-run
vendor/bin/standards
```

### Upstream Sync (Project → Standards)

**When to run:** When you've tagged new standards in your docs

```bash
# Extract tagged standards and sync to central repo
# (Requires dranzd/standards-cli configured separately)
standards-cli sync-up
```

### Complete Workflow Example

1. **Update the library:**
   ```bash
   make update
   # or: composer update dranzd/standards-doc
   ```

2. **Check what changed:**
   ```bash
   make standards-diff
   ```

3. **Sync to docs/standards/:**
   ```bash
   make standards-sync-down
   ```

4. **Review changes** in `docs/standards/`

5. **Apply changes** to your project if needed

---

## For AI Assistants and Contributors

### When Writing Documentation

1. **Check existing standards first** — Look in `docs/standards/` (after sync) or `vendor/dranzd/standards-doc/standards/`
2. **Tag appropriately** — Use `@standard` for org-wide rules, `@best-practice` for project-specific guidelines worth sharing
3. **Don't modify standards directly** — If a standard needs changing, flag it for review and update via the sync process
4. **Reference standards explicitly** — When applying a standard, cite it so readers know it's not arbitrary
5. **Document overrides** — If standards don't fit, document why in your project docs

### When to Tag

Tag content when:
- Establishing a new pattern worth sharing across projects
- Documenting a solved problem that others might face
- Creating reusable architectural guidance

### When to Override

Document overrides when:
- Legacy constraints prevent following a standard
- Project-specific requirements conflict with standards
- A standard doesn't fit the domain context

---

## Directory Reference

```
docs/
├── README.md                          # This documentation index
├── documentation-process.md           # This file — process documentation
├── domain-vision.md                   # @best-practice Business context
├── architecture.md                    # Reference standards, add project details
├── technical_design.md                # Mix of standards and project specifics
├── adr/                               # Architectural Decision Records
│   └── 001-event-getter-prefix.md     # Tag with @standard when elevating
├── standards/                         # READ-ONLY: Synced from library
│   ├── architecture/
│   ├── ddd/
│   └── event-sourcing/
└── ...

vendor/dranzd/standards-doc/           # External standards source (read-only)
├── standards/
│   ├── architecture/
│   ├── ddd/
│   └── event-sourcing/
└── docs/
    ├── README.md
    └── sync-guide.md
```

---

## Available Make Commands

| Command | Purpose |
|---------|---------|
| `make standards-diff` | Show differences between vendor and docs/standards/ |
| `make standards-dry-run` | Preview what would be synced |
| `make standards-sync-down` | Sync standards from vendor to docs/standards/ |

---

## Related

- [Agent Workflow](agent_workflow.md) — Guidelines for AI contributors
- [Standards Library](https://github.com/dranzd/standards-doc) — Central standards repository
- [Standards Sync Guide](https://github.com/dranzd/standards-doc/blob/main/docs/sync-guide.md) — Detailed sync instructions
