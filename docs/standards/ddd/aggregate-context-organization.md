<!-- hash: d992fc0fbf9cf8353401f671c69d6a8b27097202341c892cb40590e54caa3317 -->
# aggregate-context-organization

Category: ddd
Status: stable
Source: storebunk-pos

---

Domain models must be organized by bounded context with each context containing its aggregate, value objects, events, and repository interfaces. This maintains context boundaries and model consistency.

### Namespaces
- Domain Model: `Dranzd\StorebunkPos\Domain\Model\{Context}\`
- Domain Events: `Dranzd\StorebunkPos\Domain\Model\{Context}\Event\`
- Domain Value Objects: `Dranzd\StorebunkPos\Domain\Model\{Context}\ValueObject\`
- Domain Repository Interfaces: `Dranzd\StorebunkPos\Domain\Model\{Context}\Repository\`
- Domain Services: `Dranzd\StorebunkPos\Domain\Service\`
- Application Commands: `Dranzd\StorebunkPos\Application\{Context}\Command\`
- Application Handlers: `Dranzd\StorebunkPos\Application\{Context}\Command\Handler\`
- Application Read Models: `Dranzd\StorebunkPos\Application\{Context}\ReadModel\`
- Infrastructure: `Dranzd\StorebunkPos\Infrastructure\{Context}\{Layer}\`
- Shared Exceptions: `Dranzd\StorebunkPos\Shared\Exception\`
- Test Stubs: `Dranzd\StorebunkPos\Tests\Stub\Service\`

---

---

## Source File
docs/folder-structure.md
