# ADR-003: Command Structure Aligned to the storebunk-inventory Standard

## @standard: command-structure
@category: architecture
@status: stable

All application commands are plain immutable message classes: public constructor, `public readonly` primitive properties, no static factories, no value-object accessors. Handlers own all primitive-to-value-object conversion.

**Status:** Accepted
**Date:** 2026-08-12
**Context:** Application Layer Commands
**Supersedes:** [ADR-002](002-command-primitive-parameters.md)

## Decision

`storebunk-inventory` is the reference standard for all independent StoreBunk
libraries. Its command shape replaces the ADR-002 factory pattern:

1. **Public constructor, instantiated with `new`.** No private constructors,
   no domain-language static factory methods.
2. **`public readonly` promoted properties, primitives only.** Consumers and
   handlers read properties directly (`$command->terminalId`); commands expose
   no accessor methods and import no domain value objects.
3. **Handlers convert primitives to value objects.** All
   `SomeId::fromNative(...)` / `VO::fromString(...)` construction happens in the
   command handler, never in the command.
4. **Empty payload, auto-generated message UUID.** The parent call is
   `parent::__construct(messageUuid: '', messageName: self::expectedMessageName(), payload: [])`.
   The base class generates a UUID on first read. Callers that need a
   deterministic command id (offline-sync idempotency) use the base-class
   `withMessageUuid()` method instead of a constructor parameter.
5. **Message names never change.** `expectedMessageName()` keeps returning the
   exact string it returns today — see [ADR-004](004-message-name-immutability.md).

## Implementation Pattern

```php
/**
 * RegisterTerminal
 *
 * Command to register a new terminal at a branch.
 */
final class RegisterTerminal extends AbstractCommand
{
    public function __construct(
        public readonly string $terminalId,
        public readonly string $branchId,
        public readonly string $name
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.register';
    }
}
```

```php
final class RegisterTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    public function __invoke(RegisterTerminal $command): void
    {
        $terminal = Terminal::register(
            TerminalId::fromNative($command->terminalId),
            BranchId::fromNative($command->branchId),
            $command->name
        );

        $this->terminalRepository->store($terminal);
    }
}
```

## Rationale

- **One standard across libraries.** Every deviation between sibling libraries
  is a rule someone has to remember. Inventory is the canonical template; POS
  follows it.
- **The ADR-002 pattern did not deliver its own goal.** Its rationale was
  consumer independence from internal value objects, yet its commands imported
  domain VOs to return them from accessors — the coupling moved into the
  command class instead of disappearing. Primitives-in, handler-converts
  achieves the stated goal with less machinery.
- **Less code per command.** No factory, no accessors, no payload array to keep
  in sync with the properties.

## Consequences

### Positive
- Command classes are uniform with storebunk-inventory and trivially readable.
- Handlers are the single home for VO construction and validation entry.
- Tests and demo code instantiate commands with plain `new`.

### Negative
- Loses the domain-language factory names (`CancelOrder::because(...)`).
- One-time migration of every command, handler, test, and demo call site.

## Migration Notes

All instantiations change from `CancelOrder::because($id, $reason)` to
`new CancelOrder($id, $reason)`. The offline-sync flows that previously passed
`$commandId` through the factory now use
`(new StartNewOrderOffline($sessionId, $orderId))->withMessageUuid($commandId)`.
