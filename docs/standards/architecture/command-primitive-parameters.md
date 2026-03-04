<!-- hash: a26c5933eaab2204b45d9aaecdbd842bd0fb605bc1553a783ef5053daf3c2767 -->
# command-primitive-parameters

Category: architecture
Status: stable
Source: storebunk-pos

---

Commands must accept primitive values only and use expressive static factory methods. This maintains consumer independence from internal value objects and provides intention-revealing APIs.

All application commands must:

1. **Accept primitive values only** - Commands are consumer-facing and must not expose internal value objects
2. **Use domain-language factory methods** - Commands instantiate via expressive static factory methods instead of `new`
3. **Remain immutable** - Commands are immutable value objects

---

## Source File
docs/adr/002-command-primitive-parameters.md
