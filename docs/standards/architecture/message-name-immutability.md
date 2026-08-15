<!-- hash: c0401edefbb35286f4fb8dc5fbe25ca796f8c4bbf2481075ffab358a2d412fc0 -->
# message-name-immutability

Category: architecture
Status: stable
Source: storebunk-pos

---

The name strings held by commands and events (`expectedMessageName()`) are frozen once released. They are never renamed — not for consistency, style, or alignment with other libraries — unless an actual blocker forces it. Class names may change; the name string a class holds may not.

**Status:** Accepted
**Date:** 2026-08-12
**Context:** All messages — domain events and application commands

---

## Source File
docs/adr/004-message-name-immutability.md
