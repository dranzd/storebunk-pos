---
description: Verify we are not on a protected branch before making code changes
---

# Branch Protection Workflow

Before making any code edits, always verify the current Git branch.

## Protected Branches

The following branches are **protected** and must NOT be edited directly:
- `main`
- `master`
- `dev`
- `develop`

## Steps

1. Run `git branch --show-current` to check the current branch.
2. If on a protected branch, **STOP** and suggest switching to an appropriate branch:
   - For features: `feature/<task-id>-<short-description>` (e.g., `feature/1001-base-classes`)
   - For hotfixes: `hotfix/<task-id>-<short-description>` (e.g., `hotfix/fix-shift-close`)
   - For chores/docs: `chore/<short-description>` (e.g., `chore/update-docs`)
3. Suggest the command: `git checkout -b <branch-name>`
4. Only proceed with edits after confirming we are on a non-protected branch.

## Branch Naming Convention

| Type | Pattern | Example |
|------|---------|---------|
| Feature | `feature/<task-id>-<description>` | `feature/3001-shift-aggregate` |
| Hotfix | `hotfix/<task-id>-<description>` | `hotfix/fix-cash-variance` |
| Issue Fix | `fix/<issue-id>-<short-description>` | `fix/8001-multi-terminal-stateless` |
| Chore | `chore/<description>` | `chore/update-dependencies` |
| Docs | `docs/<description>` | `docs/initial-documentation` |

---

## Issue Resolution Workflow

When implementing a fix for a reported issue in `docs/reported-issues/`:

1. **Branch** — create a dedicated branch before any code changes:
   ```
   git checkout -b fix/<issue-id>-<short-description>
   ```
   Example: `fix/8001-multi-terminal-stateless`

2. **Implement** — make all code changes and update the issue file:
   - Set `**Status:** Resolved` and `**Resolved:** YYYY-MM-DD` in the issue file header
   - Fill the `## Resolution` section with a summary of what was done
   - Remove the issue from `docs/reported-issues/open-issues.md`

3. **Commit** — once all tests pass, commit with a structured message:
   ```
   fix(<issue-id>): <short human-readable description>

   Resolves reported issue #<issue-id>.
   <One or two sentences describing what changed and why.>
   ```
   Example:
   ```
   fix(8001): make MultiTerminalEnforcementService stateless

   Resolves reported issue #8001.
   Removed in-memory state arrays and mutation methods. Assert methods now
   accept read-model-sourced state as arguments so invariants are enforced
   across HTTP requests and processes.
   ```

4. **Confirm** — run the full test suite and verify all tests pass before suggesting a merge.

5. **Merge suggestion** — after confirmation, suggest merging to `main`:
   ```
   git checkout main
   git merge --no-ff fix/<issue-id>-<short-description> -m "fix(<issue-id>): <description>"
   git branch -d fix/<issue-id>-<short-description>
   ```
   Present this as a suggestion for the owner to approve and execute — do not merge automatically.
