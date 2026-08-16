<!-- hash: 30082f50f6d9b3bac8d35dce948dadcf3bb19b0259ac528e763e72b6132be818 -->
# command-structure

Category: architecture
Status: stable
Source: storebunk-pos

---

All application commands are plain immutable message classes: public constructor, `public readonly` primitive properties, no static factories, no value-object accessors. Handlers own all primitive-to-value-object conversion.

**Status:** Accepted
**Date:** 2026-08-12
**Context:** Application Layer Commands
**Supersedes:** [ADR-002](002-command-primitive-parameters.md)

---

## Source File
docs/adr/003-command-structure-inventory-alignment.md
