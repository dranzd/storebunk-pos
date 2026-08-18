<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateEvent;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\EventStore;
use Dranzd\StorebunkPos\Shared\Exception\ConcurrencyException;

/**
 * FileEventStore
 *
 * File-backed event store for the demo CLI. Every demo command runs in its
 * own PHP process, so an in-memory store loses all aggregates between steps
 * of a scenario. This implementation persists events to a JSON file on every
 * append and reloads them on construction. Demo-only — not for production.
 */
final class FileEventStore implements EventStore
{
    /** @var array<string, array<int, AggregateEvent>> */
    private array $events = [];

    /**
     * Streams that cannot be ordered (two events claiming one version),
     * keyed by aggregate id with the reason. Computed once at load: reading
     * one throws, and the projection path skips it rather than replaying
     * events whose order is undefined.
     *
     * @var array<string, string>
     */
    private array $malformed = [];

    /**
     * Events appended by THIS process and not yet flushed. save() merges these
     * onto the on-disk state under an exclusive lock instead of dumping the
     * whole in-memory snapshot, so two concurrent demo processes cannot erase
     * each other's writes.
     *
     * @var array<int, AggregateEvent>
     */
    private array $unpersisted = [];

    public function __construct(
        private readonly string $filePath
    ) {
        $this->load();
    }

    public static function defaultPath(): string
    {
        // POS_DEMO_DATA_DIR lets tests point the CLI at a scratch directory
        // instead of the real demo data.
        $dir = getenv('POS_DEMO_DATA_DIR') ?: dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . '/events.json';
    }

    public function append(AggregateEvent $event): void
    {
        $this->events[$event->getAggregateRootUuid()][] = $event;
        $this->unpersisted[] = $event;
        $this->save();
    }

    public function appendAll(array $events): void
    {
        foreach ($events as $event) {
            $this->events[$event->getAggregateRootUuid()][] = $event;
            $this->unpersisted[] = $event;
        }
        $this->save();
    }

    /**
     * @return AggregateEvent[]
     */
    public function loadEvents(string $aggregateRootUuid): array
    {
        if (isset($this->malformed[$aggregateRootUuid])) {
            throw new \RuntimeException($this->malformed[$aggregateRootUuid]);
        }

        return $this->events[$aggregateRootUuid] ?? [];
    }

    /**
     * Streams that cannot be ordered, keyed by aggregate id, with the reason
     * to show the operator. Empty for a healthy store.
     *
     * @return array<string, string>
     */
    public function malformedStreams(): array
    {
        return $this->malformed;
    }

    /**
     * @return AggregateEvent[]
     */
    public function loadEventsFromVersion(string $aggregateRootUuid, int $fromVersion): array
    {
        return array_values(
            array_filter(
                $this->events[$aggregateRootUuid] ?? [],
                static fn (AggregateEvent $event): bool => $event->getAggregateRootVersion() > $fromVersion
            )
        );
    }

    public function hasEvents(string $aggregateRootUuid): bool
    {
        return !empty($this->events[$aggregateRootUuid]);
    }

    /**
     * All persisted events keyed by aggregate root UUID, in append order.
     * Used by the demo bootstrap to rebuild process-scoped projections.
     *
     * @return array<string, array<int, AggregateEvent>>
     */
    public function allEvents(): array
    {
        // Everything, including streams that cannot be ordered. This feeds
        // read models that GUARD things — "does this shift still have active
        // sessions", "is this terminal free" — and hiding rows from a guard
        // makes the guard permissive. Claiming a terminal for a shift nobody
        // can operate is the safe failure: it is visible, and `state clear`
        // is the documented way out. Silently freeing it is not.
        //
        // Commands that depend on those guards refuse outright while any
        // stream is malformed (see demo/demo); this method never decides
        // that for them by withholding data.
        return $this->events;
    }

    public function clear(): void
    {
        // Disk is cleared BEFORE the in-memory reset: if a file cannot be
        // removed, "State cleared." would be a lie — the next process would
        // reload the supposedly cleared history.
        self::clearAt($this->filePath);

        $this->events    = [];
        $this->malformed = [];
        // Drop pending events too — a save that failed before clear()
        // must not resurrect cleared history on the next append.
        $this->unpersisted = [];
    }

    /**
     * Clear the persisted store WITHOUT constructing (and thus loading) it —
     * the recovery path for a corrupt store, whose load would throw. Takes
     * the same sidecar lock as save(); the lock file itself is retained:
     * deleting it would let a writer that already opened it keep locking the
     * old inode while new processes lock a fresh one, silently breaking
     * mutual exclusion.
     */
    public static function clearAt(string $filePath): void
    {
        $lockHandle = self::acquireLockFor($filePath);

        try {
            foreach ([$filePath, $filePath . '.tmp'] as $path) {
                if (is_file($path) && !@unlink($path)) {
                    throw new \RuntimeException(sprintf(
                        'Demo event store could not delete %s; state NOT cleared.',
                        $path
                    ));
                }
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    private function load(): void
    {
        // A SHARED lock: readers don't serialise each other, but they do
        // wait out writers and DemoReset. Without it, a reader landing in
        // DemoReset's move-aside window (data file renamed to .bak while the
        // state store commits) would see "no file" and silently report an
        // empty store even though real history exists.
        $lockHandle = self::acquireLockFor($this->filePath, LOCK_SH);

        try {
            $this->loadLocked();
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function loadLocked(): void
    {
        if (!is_file($this->filePath)) {
            return;
        }

        $raw = @file_get_contents($this->filePath);
        if ($raw === false) {
            // Fail loudly: silently starting from an empty in-memory view of
            // an existing store would later persist a truncated history.
            throw new \RuntimeException(sprintf(
                'Demo event store file %s exists but could not be read.',
                $this->filePath
            ));
        }
        if (trim($raw) === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            // A corrupt store must not masquerade as an empty one: read-only
            // demo commands would report aggregates as missing when the real
            // problem is damaged persisted history.
            throw new \RuntimeException(sprintf(
                'Demo event store file %s is not valid JSON. ' .
                'Inspect or delete the file (./demo/demo state clear) and retry.',
                $this->filePath
            ));
        }

        foreach ($decoded as $aggregateRootUuid => $records) {
            if (!is_array($records)) {
                throw $this->unreconstitutableRecord((string) $aggregateRootUuid, null, 'its record list is not an array');
            }
            foreach ($records as $index => $record) {
                if (!is_array($record) || !is_array($record['data'] ?? null)) {
                    throw $this->unreconstitutableRecord((string) $aggregateRootUuid, $index, 'the record structure is malformed');
                }
                $class = $record['class'] ?? null;
                if (!is_string($class) || !class_exists($class)) {
                    throw $this->unreconstitutableRecord(
                        (string) $aggregateRootUuid,
                        $index,
                        sprintf('event class "%s" is unknown', is_string($class) ? $class : gettype($class))
                    );
                }
                if (!is_subclass_of($class, AggregateEvent::class)) {
                    throw $this->unreconstitutableRecord(
                        (string) $aggregateRootUuid,
                        $index,
                        sprintf('class "%s" is not an aggregate event', $class)
                    );
                }
                $this->events[$aggregateRootUuid][] = $class::fromArray($record['data']);
            }
        }

        $this->recordMalformedStreams();
    }

    /**
     * Silently skipping a persisted record would reconstruct aggregates from
     * an incomplete history while presenting the store as healthy — fail
     * loudly instead, naming the offending aggregate and record.
     */
    private function unreconstitutableRecord(string $aggregateRootUuid, int|string|null $index, string $reason): \RuntimeException
    {
        return new \RuntimeException(sprintf(
            'Demo event store file %s contains an event that cannot be reconstituted ' .
            '(aggregate "%s"%s): %s. Inspect or delete the file (./demo/demo state clear) and retry.',
            $this->filePath,
            $aggregateRootUuid,
            $index === null ? '' : sprintf(', record #%s', (string) $index),
            $reason
        ));
    }

    /**
     * The lock lives on a sidecar file whose inode is never replaced or
     * deleted, so every process — writer or clearer — always serialises on
     * the same lock even though the data file is swapped atomically.
     *
     * @return resource
     */
    private function acquireLock()
    {
        return self::acquireLockFor($this->filePath);
    }

    /**
     * @return resource
     */
    private static function acquireLockFor(string $filePath, int $operation = LOCK_EX)
    {
        // Native warnings are suppressed because every failure path throws
        // its own descriptive exception.
        $lockHandle = @fopen($filePath . '.lock', 'c');
        if ($lockHandle === false) {
            throw new \RuntimeException(sprintf(
                'Demo event store cannot open lock file %s.lock.',
                $filePath
            ));
        }
        if (!flock($lockHandle, $operation)) {
            fclose($lockHandle);
            throw new \RuntimeException(sprintf(
                'Demo event store cannot lock %s.lock.',
                $filePath
            ));
        }

        return $lockHandle;
    }

    /**
     * Optimistic concurrency, enforced where it can actually be enforced.
     *
     * A handler's expected-version check runs against this process's
     * construction-time snapshot, so it cannot see what another demo process
     * appended in the meantime. This can: it runs inside the write lock,
     * against the CURRENT file. A real event store gets the same guarantee
     * from a unique (aggregate id, version) index.
     *
     * Without it, two processes that both read version N each append their
     * own version N+1 and the stream ends up with two events claiming one
     * version — which no replay can order, and which wedges every later
     * command on that aggregate.
     *
     * @param array<string, array<int, mixed>> $onDisk
     */
    private function assertVersionIsFree(array $onDisk, AggregateEvent $event): void
    {
        $aggregateId   = $event->getAggregateRootUuid();
        $rows          = $onDisk[$aggregateId] ?? [];
        $currentOnDisk = count($rows);

        if ($event->getAggregateRootVersion() === $currentOnDisk + 1) {
            return;
        }

        // A stream whose last event does not carry its row count as its
        // version cannot be ordered at all — two events claim one version.
        // Reporting THAT as a concurrency conflict invites an endless retry,
        // because no retry can ever succeed against it.
        $lastVersion = $this->versionOf($rows === [] ? null : $rows[array_key_last($rows)]);
        if ($lastVersion !== null && $lastVersion !== $currentOnDisk) {
            throw new \RuntimeException(sprintf(
                'Demo event store: the history of "%s" is malformed — %d events but the last claims '
                . 'version %d, so two of them claim the same version and no replay can order them. '
                . 'It cannot be appended to or repaired in place. Run ./demo/demo state clear.',
                $aggregateId,
                $currentOnDisk,
                $lastVersion
            ));
        }

        // Events are appended in order, so a well-formed stream's row count
        // IS its current version; the next event must claim the one after it.
        throw ConcurrencyException::forAggregate(
            $aggregateId,
            $event->getAggregateRootVersion() - 1,
            $currentOnDisk
        );
    }

    /**
     * A stream whose last event does not carry its row count as its version
     * has two events claiming one version: no replay can order them, and no
     * retry can ever append to it. Data written before this store checked
     * versions can be in that state, so the error has to say what is wrong
     * and what fixes it — a bare concurrency conflict reads as transient and
     * invites an endless retry.
     *
     * Recorded per aggregate, NOT thrown for the whole store: one corrupt
     * stream must not stop every other command from running.
     */
    private function recordMalformedStreams(): void
    {
        $this->malformed = [];

        foreach ($this->events as $aggregateRootUuid => $events) {
            if ($events === []) {
                continue;
            }

            $lastVersion = $events[array_key_last($events)]->getAggregateRootVersion();
            if ($lastVersion === count($events)) {
                continue;
            }

            $this->malformed[$aggregateRootUuid] = sprintf(
                'Demo event store: the history of "%s" is malformed — %d events but the last claims '
                . 'version %d, so two of them claim the same version and no replay can order them. '
                . 'It cannot be repaired in place. Run ./demo/demo state clear.',
                $aggregateRootUuid,
                count($events),
                $lastVersion
            );
        }
    }

    /**
     * The stored version of a persisted row, or null when the row is not in
     * the shape this store writes.
     *
     * @param mixed $row
     */
    private function versionOf($row): ?int
    {
        if (!is_array($row)) {
            return null;
        }

        $version = $row['data']['metadata'][AggregateEvent::META_AGGREGATE_ROOT_VERSION] ?? null;

        return is_int($version) ? $version : null;
    }

    private function save(): void
    {
        $lockHandle = $this->acquireLock();

        try {
            // Re-read the file INSIDE the lock and append only this process's
            // unpersisted events, so concurrent demo processes never overwrite
            // each other with stale construction-time snapshots.
            $raw = false;
            if (is_file($this->filePath)) {
                $raw = @file_get_contents($this->filePath);
                if ($raw === false) {
                    // An existing store that cannot be read is NOT an empty
                    // store — replacing it would erase all persisted history.
                    throw new \RuntimeException(sprintf(
                        'Demo event store file %s exists but could not be read; ' .
                        'refusing to replace it. Event NOT persisted.',
                        $this->filePath
                    ));
                }
            }
            if (is_string($raw) && trim($raw) !== '') {
                $onDisk = json_decode($raw, true);
                if (!is_array($onDisk)) {
                    // Corrupt store file (e.g. torn write from a crash). Bail out
                    // rather than overwrite it with only this process's events —
                    // that would silently erase every other aggregate's history.
                    throw new \RuntimeException(sprintf(
                        'Demo event store file %s is not valid JSON; refusing to overwrite it. ' .
                        'Inspect or delete the file (./demo/demo state clear) and retry.',
                        $this->filePath
                    ));
                }
            } else {
                $onDisk = [];
            }

            foreach ($this->unpersisted as $event) {
                $this->assertVersionIsFree($onDisk, $event);

                $onDisk[$event->getAggregateRootUuid()][] = [
                    'class' => get_class($event),
                    'data'  => $event->toArray(),
                ];
            }

            $encoded = json_encode($onDisk, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \RuntimeException(
                    'Demo event store could not JSON-encode its events; event NOT persisted.'
                );
            }
            $encoded .= PHP_EOL;

            // Write the full new state to a temp file in the same directory,
            // then rename it over the store atomically. The previous history
            // is never truncated in place, so a failed or partial write can
            // no longer corrupt it — the old file simply survives untouched.
            $tmpPath = $this->filePath . '.tmp';
            $written = @file_put_contents($tmpPath, $encoded);
            if ($written !== strlen($encoded)) {
                @unlink($tmpPath);
                throw new \RuntimeException(sprintf(
                    'Demo event store failed to write %s (wrote %s of %d bytes); ' .
                    'event NOT persisted, existing history left untouched.',
                    $tmpPath,
                    $written === false ? 'none' : (string) $written,
                    strlen($encoded)
                ));
            }
            if (!rename($tmpPath, $this->filePath)) {
                @unlink($tmpPath);
                throw new \RuntimeException(sprintf(
                    'Demo event store failed to replace %s with the new state; ' .
                    'event NOT persisted, existing history left untouched.',
                    $this->filePath
                ));
            }

            $this->unpersisted = [];
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}
