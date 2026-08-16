# ADR-005: Accepted Deviations from the storebunk-inventory Standard

## @standard: accepted-deviations
@category: architecture
@status: stable

storebunk-inventory is the reference standard for StoreBunk independent libraries. This ADR records the places where storebunk-pos knowingly deviates and will NOT be refactored. Do not file these as inconsistencies or "fix" them in standardization passes.

**Status:** Accepted
**Date:** 2026-08-12
**Context:** Whole library, relative to the storebunk-inventory reference

## Deviation 1 — Aggregate-first Application folder layout

- **Inventory (standard):** layer-first — `Application/Command/<Aggregate>/`,
  `Application/Query/<Aggregate>/`.
- **POS (kept as is):** aggregate-first — `Application/<Aggregate>/Command/`,
  `Application/<Aggregate>/ReadModel/`.
- **Why kept:** the difference is purely cosmetic; both layouts group a
  command with its handler. Moving every class buys no behavior and churns
  every import in the library. New POS code follows the existing POS layout.

## Deviation 2 — No application query layer

- **Inventory (standard):** ships `Application/Query/` with Query + Handler
  pairs and `Shared/QueryResult`.
- **POS (kept as is):** exposes only per-aggregate read-model **interfaces**
  (`TerminalReadModelInterface`, `ShiftReadModelInterface`,
  `PosSessionReadModelInterface`). No query classes, no query bus contract.
- **Why kept:** no consumer contract currently needs one. It is up to each
  consumer to build its own read model, in whatever shape it wants, from the
  domain events. If a shared query contract becomes genuinely needed, adding
  one is additive and can follow the inventory pattern then.

## Deviation 3 — Message name scheme

- **Inventory (standard):**
  `dranzd.storebunk.inventory.command.<aggregate>.<kebab-case-action>`.
- **POS (kept as is):** `storebunk.pos.<aggregate>.<snake_case_action>`.
- **Why kept:** message/event name strings are immutable once released — see
  [ADR-004](004-message-name-immutability.md). Renaming would break persisted
  event streams and subscriptions; alignment is never worth that.

## What is NOT a deviation

Everything else follows the inventory standard, in particular the command
structure defined by [ADR-003](003-command-structure-inventory-alignment.md).
When inventory and POS conflict and no ADR here covers the difference, the
inventory pattern wins and POS should be brought in line.
