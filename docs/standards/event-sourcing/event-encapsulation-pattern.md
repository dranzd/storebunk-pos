<!-- hash: 29923616e65facf0f35da23b673d24253637dbfab446f52b9bbb9bff31ec9ff7 -->
# event-encapsulation-pattern

Category: event-sourcing
Status: stable
Source: storebunk-pos

---

All domain events must use private properties (not readonly) with public get-prefixed getter methods. This avoids PHPStan conflicts and provides stable abstraction for consumers. All public methods on event classes must be declared final.

**Status:** Accepted
**Date:** 2026-03-02
**Applies to:** All domain events across all bounded contexts in `src/Domain/Model/*/Event/`

---

---

## Source File
docs/adr/001-event-getter-prefix.md
