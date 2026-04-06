# AI Documentation Policy

**Effective Date**: April 6, 2025  
**Status**: Active  
**Scope**: All AI agents working on storebunk-pos project

---

## Policy Overview

This document establishes clear guidelines for how AI agents handle project documentation. The goal is to maintain consistency, clarity, and prevent unauthorized modifications to project records.

---

## Core Rules

### Rule 1: Raw Discussions are Off-Limits

**Definition**: Raw discussions are conversations, notes, or informal records created outside of this project (in other projects, external discussions, or ad-hoc conversations).

**AI Policy**:
- ❌ **DO NOT** add new raw-discussion documents
- ❌ **DO NOT** modify existing raw-discussion documents
- ✅ **ONLY** if explicitly told to do so by the user

**Location**: `docs/raw-discussions/`

**Examples**:
- Notes from meetings with external teams
- Informal discussion threads
- Ad-hoc problem-solving conversations
- Reference materials from other projects

**Rationale**: These represent external input and should only be modified with explicit user direction to preserve their original context.

---

### Rule 2: Specifications, Analysis & Implementation Docs

**Definition**: These are formal, structured documents created during project work - analysis reports, implementation guides, technical specifications, and architectural decisions.

**AI Policy**:
- ✅ **CAN** create new specification documents when solving project problems
- ✅ **CAN** modify specification documents during work (for accuracy, clarity, consistency)
- ✅ **MUST** move completed specs from temporary locations to proper folders
- ✅ **SHOULD** maintain consistent formatting and naming

**Location**: `docs/specifications/`

**Examples**:
- Event pattern analysis reports
- Implementation guides and strategies
- Technical specifications
- Architecture decisions
- Problem analysis and solutions
- Verification and validation reports

**Rationale**: These documents are project artifacts that evolve as work progresses. AI should maintain them as part of deliverables.

---

### Rule 3: Consistent Naming Convention

**Standard**: All documentation files must use **kebab-case** (lowercase with hyphens).

**Format**: `<domain>-<type>-<descriptor>.md`

**Examples** ✅:
- `event-pattern-analysis.md`
- `event-pattern-fix-strategy.md`
- `event-pattern-implementation-summary.md`
- `payment-processing-spec.md`
- `terminal-sync-design.md`

**Counter-examples** ❌:
- `EventPatternAnalysis.md` (PascalCase - wrong)
- `event_pattern_analysis.md` (snake_case - wrong)
- `20250406-0001-event-pattern-analysis.md` (date-prefixed - wrong)
- `INDEX.md` (all caps - wrong)

**Rationale**: Consistent naming enables easy discovery and maintains project professionalism.

---

## Documentation Hierarchy

### Level 1: Raw Discussions
- **Folder**: `docs/raw-discussions/`
- **Content**: External conversations, informal notes
- **AI Permission**: Read-only unless explicitly directed
- **Naming**: Any format acceptable (user may have external conventions)
- **Examples**: meeting notes, external feedback, reference materials

### Level 2: Specifications & Analysis
- **Folder**: `docs/specifications/`
- **Content**: Formal analysis, implementation guides, technical specs
- **AI Permission**: Full (create, modify, maintain)
- **Naming**: kebab-case required
- **Examples**: event patterns, architecture decisions, fix strategies

### Level 3: Architecture & Design
- **Folder**: `docs/` (root level or `docs/adr/`)
- **Content**: Long-term architectural decisions
- **AI Permission**: Modify existing; create only with explicit approval
- **Naming**: kebab-case required
- **Examples**: architecture.md, core_design.md, domain_vision.md

### Level 4: Standards & Process
- **Folder**: `docs/standards/`, `docs/` (root)
- **Content**: Project standards, procedures, workflows
- **AI Permission**: Modify only for updates; create only with explicit approval
- **Naming**: kebab-case required
- **Examples**: documentation-process.md, agent_workflow.md

### Level 5: Issues & Reports
- **Folder**: `docs/reported-issues/`
- **Content**: Bug reports, issues submitted by users
- **AI Permission**: Read-only (may reference in analysis/specs)
- **Naming**: As provided by user
- **Examples**: User-submitted issues, bug reports

---

## When AI Agents Can Create Specs

✅ **AI SHOULD create** specification documents when:
- Solving a reported problem (analysis, root cause, fix strategy)
- Proposing architecture changes (tech specs, design docs)
- Documenting implementation work (what was done, why, verification)
- Creating guides for team members (how-tos, patterns, templates)
- Validating requirements (validation reports, test strategies)

❌ **AI MUST NOT create** without explicit approval:
- Long-term architectural vision documents
- Project standards or policy documents
- Onboarding or process documentation
- Decision-making frameworks
- Strategic planning documents

---

## When Moving/Reorganizing Docs

**Trigger**: AI completes work that produced documentation in wrong location or with wrong naming.

**Process**:
1. Identify what the document is (spec, analysis, implementation guide)
2. Determine correct destination folder
3. Convert filename to kebab-case
4. Move file to correct location
5. Update all internal cross-references
6. Update index files if applicable
7. Note the reorganization for user

**Example**:
```
Before: docs/raw-discussions/20250406-0001-event-pattern-analysis.md
After:  docs/specifications/event-pattern-analysis.md
Action: Updated cross-references in event-pattern-index.md
```

---

## Documentation Types & Storage

### Specification Documents
- **Purpose**: Define technical solution, implementation strategy, architectural changes
- **Folder**: `docs/specifications/`
- **Naming**: `<feature>-<type>.md` (kebab-case)
- **Examples**: 
  - `event-pattern-analysis.md`
  - `terminal-sync-specification.md`
  - `payment-gateway-integration-guide.md`

### Analysis Reports
- **Purpose**: Validate problems, analyze root causes, compare options
- **Folder**: `docs/specifications/`
- **Naming**: `<topic>-analysis.md` (kebab-case)
- **Examples**:
  - `performance-bottleneck-analysis.md`
  - `database-schema-analysis.md`

### Implementation Guides
- **Purpose**: Step-by-step instructions, code templates, checklists
- **Folder**: `docs/specifications/`
- **Naming**: `<feature>-fix-strategy.md` or `<feature>-implementation-guide.md` (kebab-case)
- **Examples**:
  - `event-pattern-fix-strategy.md`
  - `caching-implementation-guide.md`

### Summary Reports
- **Purpose**: Executive summaries, completion reports, status updates
- **Folder**: `docs/specifications/`
- **Naming**: `<topic>-summary.md` or `<topic>-implementation-complete.md` (kebab-case)
- **Examples**:
  - `event-pattern-implementation-summary.md`
  - `refactor-project-summary.md`

### Index/Navigation Files
- **Purpose**: Help users navigate related documents
- **Folder**: `docs/specifications/` (or same folder as docs being indexed)
- **Naming**: `<topic>-index.md` (kebab-case)
- **Examples**:
  - `event-pattern-index.md`
  - `migration-index.md`

---

## Cross-References & Links

**Rule**: Always use relative paths with kebab-case filenames

✅ **Correct**:
```markdown
See [`event-pattern-fix-strategy.md`](./event-pattern-fix-strategy.md) for implementation steps.
For details, read [`event-pattern-analysis.md`](./event-pattern-analysis.md).
```

❌ **Wrong**:
```markdown
See [fix strategy](./20250406-0001-fix-strategy.md) for implementation steps.
For details, read [../raw-discussions/20250406-0001-event-pattern-analysis.md](../raw-discussions/20250406-0001-event-pattern-analysis.md).
```

---

## Git Commit Messages for Documentation

When committing documentation work:

**Format**: `docs: <action> <description>`

**Examples**:
```
docs: move event-pattern specs to specifications folder
docs: create event-pattern-fix-strategy specification
docs: update event-pattern-index with new cross-references
docs: refactor to use kebab-case naming convention
```

---

## AI Agent Responsibilities

### Before Creating Documentation

1. ✅ Verify the document type (spec, analysis, guide, etc.)
2. ✅ Confirm the appropriate folder location
3. ✅ Choose kebab-case filename
4. ✅ Check for existing related documentation
5. ✅ Plan cross-references

### When Modifying Existing Documentation

1. ✅ Update all internal links if moving/renaming
2. ✅ Preserve original intent and structure
3. ✅ Maintain consistent voice and formatting
4. ✅ Add notes for user about changes made
5. ✅ Test all cross-references

### When Completing Work

1. ✅ Organize all artifacts (code, tests, docs)
2. ✅ Move docs to correct locations
3. ✅ Rename to kebab-case if needed
4. ✅ Update related index files
5. ✅ Notify user of documentation structure

### Memory & Communication

1. ✅ All AI agents should know this policy
2. ✅ Communicate it to new agents joining the project
3. ✅ Refer to this policy when making documentation decisions
4. ✅ Flag violations and correct them immediately

---

## Exceptions & Appeals

**When can exceptions be made?**

Exceptions to this policy can only be granted by explicit user direction:

```
User says: "Create a new raw-discussion document about X"
→ AI may create in docs/raw-discussions/ with user's preferred naming

User says: "Keep this doc with old filename"
→ AI may preserve old naming if explicitly instructed

User says: "Put specs in a different folder"
→ AI may move to user-specified location
```

**No exceptions without explicit direction.**

---

## Quick Reference Checklist

Before creating or modifying documentation:

- [ ] Is this a specification/analysis/implementation doc?
- [ ] Should it go in `docs/specifications/`?
- [ ] Is the filename in kebab-case?
- [ ] Are all internal links using kebab-case names?
- [ ] Have I updated related index files?
- [ ] Is this a raw discussion? (If yes: Do I have explicit permission?)
- [ ] Have I notified the user of documentation changes?

---

## Questions & Clarifications

**Q: Can I create a raw-discussion document if it helps explain the work?**  
A: No. Create it in `docs/specifications/` instead. Raw discussions are reserved for actual external conversations.

**Q: What if the user has a different naming convention preference?**  
A: Ask for clarification. This policy assumes kebab-case unless explicitly overridden.

**Q: Should I update old documentation when I find errors?**  
A: If it's in `docs/specifications/`, yes. If it's in `docs/raw-discussions/`, ask first.

**Q: Can I move files without user permission?**  
A: Yes, if it's part of completing work (moving docs from temporary to final locations). Always notify the user.

**Q: What about documentation in the README or root-level docs?**  
A: Treat as "Architecture & Design" level (Rule 3). Modify with care; notify user of changes.

---

## Policy Version & Updates

**Current Version**: 1.0  
**Effective**: April 6, 2025  
**Last Updated**: April 6, 2025  
**Next Review**: June 6, 2025 (or after major project changes)

To update this policy, users should modify this file directly or request AI agents do so with explicit instructions.

---

## Enforcement

This policy is:
- ✅ **Binding** on all AI agents working on storebunk-pos
- ✅ **Enforceable** through peer review and user feedback
- ✅ **Transparent** (published in project docs)
- ✅ **Flexible** (can be updated by user with explicit direction)

**Responsibility**: All AI agents to follow and communicate this policy.

---

*AI Documentation Policy for storebunk-pos project*  
*Established April 6, 2025*  
*All AI agents must be aware of and follow this policy*
