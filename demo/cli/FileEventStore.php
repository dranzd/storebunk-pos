<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateEvent;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\EventStore;

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
        return dirname(__DIR__) . '/data/events.json';
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
        return $this->events[$aggregateRootUuid] ?? [];
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
        return $this->events;
    }

    public function clear(): void
    {
        $this->events = [];
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    private function load(): void
    {
        if (!is_file($this->filePath)) {
            return;
        }

        $raw = file_get_contents($this->filePath);
        if ($raw === false || $raw === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $aggregateRootUuid => $records) {
            foreach ($records as $record) {
                $class = $record['class'] ?? '';
                if (
                    !is_string($class)
                    || !class_exists($class)
                    || !is_subclass_of($class, AggregateEvent::class)
                ) {
                    continue;
                }
                $this->events[$aggregateRootUuid][] = $class::fromArray($record['data']);
            }
        }
    }

    private function save(): void
    {
        $handle = fopen($this->filePath, 'c+');
        if ($handle === false) {
            return;
        }

        flock($handle, LOCK_EX);

        // Re-read the file INSIDE the lock and append only this process's
        // unpersisted events, so concurrent demo processes never overwrite
        // each other with stale construction-time snapshots.
        $raw = stream_get_contents($handle);
        if (is_string($raw) && trim($raw) !== '') {
            $onDisk = json_decode($raw, true);
            if (!is_array($onDisk)) {
                // Corrupt store file (e.g. torn write from a crash). Bail out
                // rather than overwrite it with only this process's events —
                // that would silently erase every other aggregate's history.
                flock($handle, LOCK_UN);
                fclose($handle);
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
            $onDisk[$event->getAggregateRootUuid()][] = [
                'class' => get_class($event),
                'data'  => $event->toArray(),
            ];
        }

        $encoded = json_encode($onDisk, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        rewind($handle);
        ftruncate($handle, 0);
        $written = fwrite($handle, $encoded);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        // Only forget the pending events once they demonstrably reached disk;
        // a short write keeps them queued for the next save attempt.
        if ($written === strlen($encoded)) {
            $this->unpersisted = [];
        }
    }
}
