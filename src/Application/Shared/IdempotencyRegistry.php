<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shared;

use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

/**
 * Remembers which commands have been processed, so a redelivery is absorbed
 * instead of doing the work twice.
 *
 * A command id alone cannot answer that: two different commands carrying one
 * id look identical to a registry that stores nothing else, so the second is
 * absorbed and its caller told it succeeded for work that never happened.
 * That is a real trap here, because deterministic ids are encouraged for
 * offline retries and "one key per order" is the obvious scheme — under it, a
 * sync would be silently swallowed by the create that used the same key, and
 * the order would sit in the pending queue forever.
 *
 * So each id is recorded WITH what it did. A repeat of the same work is a
 * redelivery; the same id claiming different work is a collision, and is
 * refused rather than absorbed.
 */
final class IdempotencyRegistry
{
    /** @var array<string, string> command id => what that command did */
    private array $processedCommandIds = [];

    /**
     * The purpose string for a command: what it does, and to what. Built in
     * one place so that a replay rebuilding the registry describes a command
     * exactly as the handler did — describing it differently would make every
     * replayed id look like a collision, and describing it not at all would
     * make it match anything.
     */
    public static function purposeFor(string $messageName, string $targetId): string
    {
        return $messageName . ':' . $targetId;
    }

    /**
     * @param string $purpose what this command does, and to what — typically
     *                        the message name plus its target id. Omit it to
     *                        ask the plain "is this id known?" question; the
     *                        collision check needs both sides to state one.
     *
     * @throws InvariantViolationException when the id is known for different work
     */
    public function hasBeenProcessed(string $commandId, string $purpose = ''): bool
    {
        $recorded = $this->processedCommandIds[$commandId] ?? null;
        if ($recorded === null) {
            return false;
        }

        // No purpose stated by the ASKER: a plain "is this id known?" lookup,
        // not a claim about what it did. A record written without one is a
        // different matter — it would match anything, disarming the check —
        // so writers must state a purpose.
        if ($purpose === '' || $recorded === $purpose) {
            return true;
        }

        // One of the two callers is wrong about what this id means. Saying so
        // is the whole point: absorbing it would report success for work that
        // was never done.
        throw InvariantViolationException::withMessage(sprintf(
            'Command id "%s" was already used for "%s" and cannot be reused for "%s"; '
            . 'a command id identifies one command instance',
            $commandId,
            $recorded,
            $purpose
        ));
    }

    /**
     * @param string $purpose what this command did — see hasBeenProcessed().
     *                        An empty one records the id as matching any
     *                        later work, so pass it wherever the answer
     *                        matters, replay included.
     */
    public function markAsProcessed(string $commandId, string $purpose = ''): void
    {
        $this->processedCommandIds[$commandId] = $purpose;
    }
}
