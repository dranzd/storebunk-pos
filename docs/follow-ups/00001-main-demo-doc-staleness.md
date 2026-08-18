# 00001-main — Stale statements in the demo documentation

**Raised:** 2026-08-18
**Source:** local multi-lens review of the shift-enforcement workstream (seventh pass)
**Status:** Open — parked deliberately, not part of the 8002/8003 work

Three statements in the demo docs are wrong, all pre-dating the shift-enforcement
branch and none of them touched by it. Parked rather than folded in, so the
branch stayed scoped to what it claimed to fix.

- `demo/README.md:198` — "The event store is in-memory only". False since the
  file-backed store landed, and it contradicts line 32 of the same file.
- `demo/README.md:36` — "(Terminal only)" describing which read models are
  rebuilt at bootstrap. Inverted: the terminal read model is the one NOT
  rebuilt in the replay loop; it is projected per command.
- `docs/demo.md:26` — references a `SimpleQueryBus`. No such class exists
  anywhere in the repo.

Worth doing as one small `docs:` commit. Check the rest of `demo/README.md`
against the code while in there — it has drifted further than `docs/demo.md`,
which the branch kept in sync.
