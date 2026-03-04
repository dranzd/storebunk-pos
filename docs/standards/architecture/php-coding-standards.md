<!-- hash: 583d072ec3f6568df3771d6ed9280a0b5e0bea0eda554ac0bd9c0797f571cf6e -->
# php-coding-standards

Category: architecture
Status: stable
Source: storebunk-pos

---

All code must follow PSR-12 style, use strict types, maintain immutability for VOs and events, avoid public getters on aggregates, and include PHPDoc for all public methods. Event accessors use get/is prefixes, properties are private (not readonly), and public methods are final by default.

- **PSR-12**: Code style enforced via PHP_CodeSniffer.
- **Strict Types**: `declare(strict_types=1);` in all files.
- **Immutability**: All Value Objects and Events are immutable.
- **No Public Getters on Aggregates**: All reads go through CQRS projections.
- **PHPDoc**: All public methods must have PHPDoc blocks.
- **Event Accessor Naming**: All domain event getter methods use the `get` prefix (e.g., `getTerminalId()`, `getShiftId()`). Boolean accessors use the `is` prefix (e.g., `isActive()`). See [ADR-001](adr/001-event-getter-prefix.md) for the full rationale.
- **Event Properties**: All domain event properties are `private` (never `public readonly`) to avoid PHPStan `property.readOnlyAssignNotInConstructor` errors. See [ADR-001](adr/001-event-getter-prefix.md).
- **`final` Methods**: All public methods on concrete classes are declared `final` by default.

---

## Source File
docs/technical_design.md
