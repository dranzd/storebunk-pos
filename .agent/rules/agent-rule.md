---
trigger: always_on
glob:
description:
---

## Common Library Issues

If during any task you discover a bug, missing feature, incorrect behaviour, or improvement opportunity in any `dranzd/*` common library (`common-event-sourcing`, `common-cqrs`, `common-valueobject`, `common-domain-assert`, `common-utils`), do NOT silently work around it in this codebase.

Instead:
1. **Report it clearly** — state which library, which class/method, and what the problem is.
2. **Describe the fix or improvement** — provide the exact change needed (e.g. corrected method signature, missing parameter, wrong return type, missing interface method).
3. **Do not re-implement** library functionality here as a workaround — the fix belongs upstream in the library itself.
4. **Note any downstream impact** — explain how the fix would affect this codebase once the library is updated.
