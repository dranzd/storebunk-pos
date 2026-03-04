<!-- hash: f21a6f72f7933233ea5bdbb479f676e2e333322f9303beef9f5247cb9012fa09 -->
# hexagonal-architecture

Category: architecture
Status: stable
Source: storebunk-pos

---

Application layer orchestrates domain objects to fulfill use cases without containing business logic. Infrastructure layer implements technology-specific adapters for domain ports. This maintains dependency inversion and technology independence.

### Application Layer
- **Orchestrates** domain objects to fulfill use cases
- **Commands** represent intentions to change state
- **Queries** represent requests for data
- **Handlers** execute commands and queries
- **Event Handlers** react to domain events for cross-aggregate coordination

---

## Source File
docs/folder-structure.md
