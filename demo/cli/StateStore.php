<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

/**
 * Persists named UUIDs and demo state to a JSON file so that
 * multiple CLI invocations can share the same session.
 */
final class StateStore
{
    private string $filePath;

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->load();
    }

    public static function defaultPath(): string
    {
        $dir = dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/demo-state.json';
    }

    private function load(): void
    {
        $this->data = $this->readFromDisk();
    }

    /**
     * Current on-disk state. Absent and empty files are an empty state;
     * unreadable or corrupt files fail loudly — silently treating them as
     * empty would later persist a state that has lost every existing key.
     *
     * @return array<string, mixed>
     */
    private function readFromDisk(): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }

        $raw = @file_get_contents($this->filePath);
        if ($raw === false) {
            throw new \RuntimeException(sprintf(
                'Demo state file %s exists but could not be read.',
                $this->filePath
            ));
        }
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf(
                'Demo state file %s is not valid JSON. ' .
                'Inspect or delete the file (./demo/demo state clear) and retry.',
                $this->filePath
            ));
        }

        return $decoded;
    }

    /**
     * The lock lives on a sidecar file whose inode is never replaced or
     * deleted, so every process serialises on the same lock even though the
     * data file is swapped atomically.
     *
     * @return resource
     */
    private function acquireLock()
    {
        // Native warnings are suppressed because every failure path throws
        // its own descriptive exception.
        $lockHandle = @fopen($this->filePath . '.lock', 'c');
        if ($lockHandle === false) {
            throw new \RuntimeException(sprintf(
                'Demo state store cannot open lock file %s.lock.',
                $this->filePath
            ));
        }
        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            throw new \RuntimeException(sprintf(
                'Demo state store cannot lock %s.lock.',
                $this->filePath
            ));
        }

        return $lockHandle;
    }

    /**
     * Apply a mutation to the CURRENT on-disk state under the sidecar lock,
     * then persist atomically. Mutating this instance's construction-time
     * snapshot instead would let two concurrent CLI processes setting
     * different keys keep only the last writer's snapshot.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $mutation
     */
    private function mutate(callable $mutation): void
    {
        $lockHandle = $this->acquireLock();

        try {
            $this->persist($mutation($this->readFromDisk()));
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * Persist the given state, committing it to $data only on success. The
     * new state is written to a temp file and atomically renamed over the
     * store, so a failed or partial write can neither corrupt the previous
     * file nor leave this instance holding unsaved state.
     *
     * @param array<string, mixed> $data
     */
    private function persist(array $data): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('Demo state could not be JSON-encoded; state NOT saved.');
        }

        // Native warnings are suppressed because every failure path throws
        // its own descriptive exception.
        $tmpPath = $this->filePath . '.tmp';
        $written = @file_put_contents($tmpPath, $encoded);
        if ($written !== strlen($encoded)) {
            @unlink($tmpPath);
            throw new \RuntimeException(sprintf(
                'Demo state file %s could not be written; state NOT saved, previous state left untouched.',
                $this->filePath
            ));
        }
        if (!@rename($tmpPath, $this->filePath)) {
            @unlink($tmpPath);
            throw new \RuntimeException(sprintf(
                'Demo state file %s could not be replaced; state NOT saved, previous state left untouched.',
                $this->filePath
            ));
        }

        $this->data = $data;
    }

    public function set(string $key, mixed $value): void
    {
        $this->mutate(static function (array $data) use ($key, $value): array {
            $data[$key] = $value;

            return $data;
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function push(string $key, mixed $value): void
    {
        $this->mutate(static function (array $data) use ($key, $value): array {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                $data[$key] = [];
            }
            $data[$key][] = $value;

            return $data;
        });
    }

    /** @return list<mixed> */
    public function getList(string $key): array
    {
        $value = $this->data[$key] ?? [];
        return is_array($value) ? array_values($value) : [];
    }

    public function remove(string $key): void
    {
        $this->mutate(static function (array $data) use ($key): array {
            unset($data[$key]);

            return $data;
        });
    }

    public function clear(): void
    {
        // Deliberately does NOT read current state first: clearing is the
        // documented remedy for a corrupt state file, so it must work even
        // when that file cannot be read or decoded.
        $lockHandle = $this->acquireLock();

        try {
            $this->persist([]);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public function filePath(): string
    {
        return $this->filePath;
    }
}
