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
| Chore | `chore/<description>` | `chore/update-dependencies` |
| Docs | `docs/<description>` | `docs/initial-documentation` |
