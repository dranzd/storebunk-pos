<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

/**
 * Coordinated reset of the demo's three persistent stores: event history,
 * key/value state, and shift-slot reservations.
 *
 * Clearing them sequentially can partially reset the demo: if event deletion
 * succeeds but a later store fails to lock or write, event history is gone
 * while stale state or reservations remain. This class acquires all three
 * stable sidecar locks in a deterministic order (events, then state, then
 * slots), stages every fallible write BEFORE committing anything, and moves
 * event history and slot state aside recoverably so a late failure restores
 * them. Run it when no other demo command is in flight — a command already
 * past bootstrap replays pre-reset events and may act on stale state.
 * Demo-only — not for production.
 */
final class DemoReset
{
    public static function clearAll(string $eventsPath, string $statePath, string $slotsPath): void
    {
        // Same stable lock inodes the stores themselves serialise on.
        $eventsLock = self::lock($eventsPath . '.lock', 'event store');

        try {
            $stateLock = self::lock($statePath . '.lock', 'state store');

            try {
                $slotsLock = self::lock($slotsPath . '.lock', 'shift-slot store');

                try {
                    self::resetAll($eventsPath, $statePath, $slotsPath);
                } finally {
                    flock($slotsLock, LOCK_UN);
                    fclose($slotsLock);
                }
            } finally {
                flock($stateLock, LOCK_UN);
                fclose($stateLock);
            }
        } finally {
            flock($eventsLock, LOCK_UN);
            fclose($eventsLock);
        }
    }

    private static function resetAll(string $eventsPath, string $statePath, string $slotsPath): void
    {
        // 1. STAGE the empty state file: the state store's directory and
        //    write path are proven usable before any history is touched.
        $stateTmp = $statePath . '.tmp';
        $written = @file_put_contents($stateTmp, '[]');
        if ($written !== 2) {
            @unlink($stateTmp);
            throw new \RuntimeException(sprintf(
                'Demo reset could not stage %s; nothing was cleared.',
                $stateTmp
            ));
        }

        // 2. Move event history ASIDE (recoverable) instead of deleting it.
        $eventsBak = $eventsPath . '.bak';
        $eventsMoved = false;
        if (is_file($eventsPath)) {
            if (!@rename($eventsPath, $eventsBak)) {
                @unlink($stateTmp);
                throw new \RuntimeException(sprintf(
                    'Demo reset could not move %s aside; nothing was cleared.',
                    $eventsPath
                ));
            }
            $eventsMoved = true;
        }
        $eventsTmp = $eventsPath . '.tmp';
        if (is_file($eventsTmp) && !@unlink($eventsTmp)) {
            @unlink($stateTmp);
            self::restoreEvents($eventsMoved, $eventsBak, $eventsPath);
            throw new \RuntimeException(sprintf(
                'Demo reset could not delete %s; nothing was cleared.',
                $eventsTmp
            ));
        }

        // 3. Move shift-slot state ASIDE (recoverable) too.
        $slotsBak = $slotsPath . '.bak';
        $slotsMoved = false;
        if (is_file($slotsPath)) {
            if (!@rename($slotsPath, $slotsBak)) {
                @unlink($stateTmp);
                self::restoreEvents($eventsMoved, $eventsBak, $eventsPath);
                throw new \RuntimeException(sprintf(
                    'Demo reset could not move %s aside; nothing was cleared.',
                    $slotsPath
                ));
            }
            $slotsMoved = true;
        }
        $slotsTmp = $slotsPath . '.tmp';
        if (is_file($slotsTmp) && !@unlink($slotsTmp)) {
            @unlink($stateTmp);
            if ($slotsMoved) {
                @rename($slotsBak, $slotsPath);
            }
            self::restoreEvents($eventsMoved, $eventsBak, $eventsPath);
            throw new \RuntimeException(sprintf(
                'Demo reset could not delete %s; nothing was cleared.',
                $slotsTmp
            ));
        }

        // 4. COMMIT the state replacement; on failure restore everything.
        if (!@rename($stateTmp, $statePath)) {
            @unlink($stateTmp);
            $restoreFailures = [];
            if ($slotsMoved && !@rename($slotsBak, $slotsPath)) {
                $restoreFailures[] = sprintf('%s from %s', $slotsPath, $slotsBak);
            }
            if ($eventsMoved && !@rename($eventsBak, $eventsPath)) {
                $restoreFailures[] = sprintf('%s from %s', $eventsPath, $eventsBak);
            }
            if ($restoreFailures !== []) {
                throw new \RuntimeException(sprintf(
                    'Demo reset could not replace %s AND could not restore %s — restore manually.',
                    $statePath,
                    implode(' and ', $restoreFailures)
                ));
            }
            throw new \RuntimeException(sprintf(
                'Demo reset could not replace %s; event and slot history were restored, nothing was cleared.',
                $statePath
            ));
        }

        // 5. Success — drop the recoverable backups.
        if ($eventsMoved) {
            @unlink($eventsBak);
        }
        if ($slotsMoved) {
            @unlink($slotsBak);
        }
    }

    private static function restoreEvents(bool $eventsMoved, string $eventsBak, string $eventsPath): void
    {
        if ($eventsMoved) {
            @rename($eventsBak, $eventsPath);
        }
    }

    /**
     * @return resource
     */
    private static function lock(string $lockPath, string $label)
    {
        // Native warnings are suppressed because every failure path throws
        // its own descriptive exception.
        $handle = @fopen($lockPath, 'c');
        if ($handle === false) {
            throw new \RuntimeException(sprintf(
                'Demo reset cannot open the %s lock file %s; nothing was cleared.',
                $label,
                $lockPath
            ));
        }
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException(sprintf(
                'Demo reset cannot lock %s; nothing was cleared.',
                $lockPath
            ));
        }

        return $handle;
    }
}
