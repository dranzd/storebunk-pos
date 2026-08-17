<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Shared\Exception;

use Throwable;

/**
 * Thrown when a shift command could not tidy up its slot reservation, so the
 * slots no longer certainly match the committed shifts.
 *
 * The two halves are kept apart deliberately: the message names the original
 * failure and the recovery step, while getPrevious() still carries the
 * original exception, so nothing is masked by the cleanup problem.
 */
class SlotCleanupFailedException extends DomainException
{
    private const RECOVERY = 'Slot state is uncertain — reconcile the shift slots before reopening this terminal.';

    /**
     * A command failed to persist AND its slot claim could not be rolled back.
     */
    public static function afterFailedCommand(
        string $shiftId,
        Throwable $original,
        Throwable $cleanupFailure
    ): self {
        return new self(
            sprintf(
                'Shift "%s" failed to persist (%s) and its slot claim could not be rolled back (%s). %s',
                $shiftId,
                $original->getMessage(),
                $cleanupFailure->getMessage(),
                self::RECOVERY
            ),
            0,
            $original
        );
    }

    /**
     * A command persisted successfully but its slots could not be released,
     * so the terminal and cashier stay blocked by a shift that is no longer
     * open.
     */
    public static function afterCommittedCommand(string $shiftId, Throwable $cleanupFailure): self
    {
        return new self(
            sprintf(
                'Shift "%s" was persisted but its slots could not be updated (%s). %s',
                $shiftId,
                $cleanupFailure->getMessage(),
                self::RECOVERY
            ),
            0,
            $cleanupFailure
        );
    }
}
