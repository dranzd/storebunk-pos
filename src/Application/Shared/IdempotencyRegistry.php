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
 * So every id is recorded WITH what it did, and both methods require it. A
 * repeat of the same work is a redelivery; the same id claiming different
 * work is a collision, and is refused rather than absorbed.
 *
 * There is deliberately no "unspecified": an id recorded without a purpose
 * could only match everything (disarming the check) or match nothing
 * (refusing legitimate redeliveries), and both have been shipped here by
 * accident. Requiring it removes the choice.
 *
 * A HOST REBUILDING THIS FROM EVENTS must pass the same purpose the handler
 * would; {@see self::purposeFor()} builds it, so the two cannot drift.
 */
final class IdempotencyRegistry
{
    /** @var array<string, string> command id => what that command did */
    private array $processedCommandIds = [];

    /**
     * The purpose string for a command: what it does, and to what. Built in
     * one place so that a replay rebuilding the registry describes a command
     * exactly as the handler did — describing it differently would make every
     * replayed id look like a collision.
     */
    public static function purposeFor(string $messageName, string $targetId): string
    {
        return $messageName . ':' . $targetId;
    }

    /**
     * @param string $purpose what this command does, and to what — from
     *                        {@see self::purposeFor()}
     *
     * @throws InvariantViolationException when this id is recorded for different work
     */
    public function hasBeenProcessed(string $commandId, string $purpose): bool
    {
        $recorded = $this->processedCommandIds[$commandId] ?? null;
        if ($recorded === null) {
            return false;
        }

        if ($recorded === $purpose) {
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
     * @param string $purpose what this command did — see hasBeenProcessed()
     */
    public function markAsProcessed(string $commandId, string $purpose): void
    {
        $this->processedCommandIds[$commandId] = $purpose;
    }
}
