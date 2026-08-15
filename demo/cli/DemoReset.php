<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

/**
 * Coordinated reset of the demo's two persistent stores.
 *
 * Clearing them sequentially can partially reset the demo: if event deletion
 * succeeds but the state store then fails to lock or write, event history is
 * gone while stale state IDs remain. This class acquires both stable sidecar
 * locks in a deterministic order (events, then state), stages every fallible
 * write BEFORE committing anything, and moves event history aside recoverably
 * so a late state failure restores it. Demo-only — not for production.
 */
final class DemoReset
{
    public static function clearAll(string $eventsPath, string $statePath): void
    {
        // Same stable lock inodes the stores themselves serialise on.
        $eventsLock = self::lock($eventsPath . '.lock', 'event store');

        try {
            $stateLock = self::lock($statePath . '.lock', 'state store');

            try {
                self::resetBoth($eventsPath, $statePath);
            } finally {
                flock($stateLock, LOCK_UN);
                fclose($stateLock);
            }
        } finally {
            flock($eventsLock, LOCK_UN);
            fclose($eventsLock);
        }
    }

    private static function resetBoth(string $eventsPath, string $statePath): void
    {
        // 1. STAGE the empty state file: the state store's directory and
        //    write path are proven usable before any event history is touched.
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
            if ($eventsMoved) {
                @rename($eventsBak, $eventsPath);
            }
            throw new \RuntimeException(sprintf(
                'Demo reset could not delete %s; nothing was cleared.',
                $eventsTmp
            ));
        }

        // 3. COMMIT the state replacement; on failure restore event history.
        if (!@rename($stateTmp, $statePath)) {
            @unlink($stateTmp);
            if ($eventsMoved && !@rename($eventsBak, $eventsPath)) {
                throw new \RuntimeException(sprintf(
                    'Demo reset could not replace %s AND could not restore %s from %s — restore it manually.',
                    $statePath,
                    $eventsPath,
                    $eventsBak
                ));
            }
            throw new \RuntimeException(sprintf(
                'Demo reset could not replace %s; event history was restored, nothing was cleared.',
                $statePath
            ));
        }

        // 4. Success — drop the event-history backup.
        if ($eventsMoved) {
            @unlink($eventsBak);
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
