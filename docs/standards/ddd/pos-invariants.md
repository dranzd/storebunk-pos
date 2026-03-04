<!-- hash: ffcc05be6e4fd60f5d6205f66d45e4c8e0f2e6dd83278f89c7962028729ac6ed -->
# pos-invariants

Category: ddd
Status: stable
Source: storebunk-pos

---

All POS business invariants must be enforced within the domain layer. These rules represent operational discipline that the POS system must maintain at all times.

1. **One cashier = one terminal per open shift**
2. **One terminal = one open shift at a time**
3. **Shift cannot close with unresolved Draft or Confirmed orders**
4. **Checkout locks order lines** — no modifications after confirmation
5. **Payment cannot apply without Confirmed state**
6. **Reservation TTL only applies in Draft phase**
7. **Confirmed orders cannot auto-expire**
8. **Cash drawer only affected by defined cash movements**
9. **No expense withdrawal in POS**
10. **POS never owns pricing, tax, stock deduction, or ledger logic**

---

---

## Source File
docs/architecture.md
