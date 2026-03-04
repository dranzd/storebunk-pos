<!-- hash: c6c4b4739c04ec6d8a0e38bd4f7d3cd0693521798eed41be221e915d79d62c82 -->
# bounded-context-dependencies

Category: architecture
Status: stable
Source: storebunk-pos

---

POS depends on other bounded contexts through ports (interfaces) only. Other BCs must never depend on POS. Integration must be event-driven with POS emitting events for downstream BCs to react independently.

POS depends on other bounded contexts through **ports (interfaces)**:

```
POS --> Ordering BC    (via OrderingServiceInterface)
POS --> Inventory BC   (via InventoryServiceInterface)
POS --> Payment BC     (via PaymentServiceInterface)
```

Other BCs never depend on POS. Integration is event-driven: POS emits events, downstream BCs react independently.

---

## Source File
docs/core_design.md
