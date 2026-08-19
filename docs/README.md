# StoreBunk POS Documentation

Welcome to the documentation for the **StoreBunk POS** library.

## Project Overview

StoreBunk POS is the **operational execution layer of retail transactions** for the StoreBunk Multi-Retail Platform. It is a domain-centric Point of Sale core designed to operate as a **framework-agnostic PHP library**, focusing on pure business logic without UI components.

Built using **Domain-Driven Design (DDD)**, **Event Sourcing (ES)**, **Hexagonal Architecture (Ports & Adapters)**, and **CQRS**.

## Documentation Index

### Concept & Philosophy
- **[Domain Vision](domain-vision.md)** — Business context, philosophy, and domain boundaries
- **[Core Design](core_design.md)** — Architectural principles (DDD, ES, Hexagonal) and layers

### Architecture & Implementation
- **[Architecture Guide](architecture.md)** — DDD, Event Sourcing, Hexagonal Architecture, CQRS patterns
- **[Domain Model](domain-model.md)** — Aggregates, Commands, Events, Policies, Invariants
- **[Folder Structure](folder-structure.md)** — Complete directory reference and conventions
- **[Technical Design](technical_design.md)** — Implementation details, coding standards, and adapters
- **[Offline Sync](offline-sync.md)** — Offline order creation, sync queue, idempotency model, and consumer integration guide
- **[Event Pattern Specification](specifications/event-pattern-specification.md)** — The `getPayload()`/`setPayload()` serialization contract every domain event implements

### Planning & Tracking
- **[Milestones](milestones.md)** — Project roadmap with phased plan and commit messages
- **[Tasks](tasks.md)** — Active task tracking
- **[Feature Specifications](features/README.md)** — Phased implementation checklist with status tracking

### Demo
- **[Demo CLI Specification](demo.md)** — Demo CLI design, commands, and scenario specifications (implemented under `demo/`; see `demo/README.md` for usage)

### Quality & Issue Tracking
- **[Reported Issues](reported-issues/README.md)** — Issue tracking system with standards, template, and resolution workflow
- **[Open Issues](reported-issues/open-issues.md)** — Active issues checklist
- **[Library Feedback](library-feedback/README.md)** — Common library feedback tracking

### Architectural Decision Records (ADR)
- **[ADR-001: Event Property Encapsulation and `get`-Prefixed Accessors](adr/001-event-getter-prefix.md)** — Why all domain events use `private` properties with `get`-prefixed getters instead of `public readonly` properties
- **[ADR-002: Command Primitive Parameters and Factory Methods](adr/002-command-primitive-parameters.md)** — *Superseded by ADR-003*; kept for historical context
- **[ADR-003: Command Structure Aligned to the storebunk-inventory Standard](adr/003-command-structure-inventory-alignment.md)** — Public constructors with readonly primitive properties; handlers own value-object conversion
- **[ADR-004: Message and Event Names Are Immutable](adr/004-message-name-immutability.md)** — Name strings are frozen once released; class renames allowed, held names are not
- **[ADR-005: Accepted Deviations from the storebunk-inventory Standard](adr/005-accepted-deviations-from-inventory-standard.md)** — Folder layout, no query layer, message-name scheme — intentional, do not "fix"
- **[ADR-006: Outbound Ordering Context Is Opaque to POS](adr/006-opaque-ordering-context.md)** — Consumer-owned context arrays pass through untyped; the consumer-side translator pattern

### Standards
- **[Coding & Design Standards](standards/)** — The rules this library is held to, in three trees: `architecture/`, `ddd/`, `event-sourcing/`. Copies synced from the shared standards repository; the local ADRs above take precedence where they differ.

### Process & Guidelines
- **[Documentation Process](documentation-process.md)** — How standards and project documentation interact, tagging conventions, and sync workflow
- **[Agent Workflow](agent_workflow.md)** — Guidelines for AI assistants contributing to this project
- **[AI Documentation Policy](ai-documentation-policy.md)** — Where a generated document belongs, how to name it, and how to cross-reference it

### Raw Materials
- **[Raw Discussions](raw-discussions/)** — Original design discussions and brainstorming
