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
        if (file_exists($this->filePath)) {
            $raw = file_get_contents($this->filePath);
            $decoded = json_decode((string) $raw, true);
            $this->data = is_array($decoded) ? $decoded : [];
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
        $data = $this->data;
        $data[$key] = $value;
        $this->persist($data);
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
        $data = $this->data;
        if (!isset($data[$key]) || !is_array($data[$key])) {
            $data[$key] = [];
        }
        $data[$key][] = $value;
        $this->persist($data);
    }

    /** @return list<mixed> */
    public function getList(string $key): array
    {
        $value = $this->data[$key] ?? [];
        return is_array($value) ? array_values($value) : [];
    }

    public function remove(string $key): void
    {
        $data = $this->data;
        unset($data[$key]);
        $this->persist($data);
    }

    public function clear(): void
    {
        $this->persist([]);
    }

    public function filePath(): string
    {
        return $this->filePath;
    }
}
