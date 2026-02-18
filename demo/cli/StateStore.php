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

    private function save(): void
    {
        file_put_contents(
            $this->filePath,
            json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->save();
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
        if (!isset($this->data[$key]) || !is_array($this->data[$key])) {
            $this->data[$key] = [];
        }
        $this->data[$key][] = $value;
        $this->save();
    }

    /** @return list<mixed> */
    public function getList(string $key): array
    {
        $value = $this->data[$key] ?? [];
        return is_array($value) ? array_values($value) : [];
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
        $this->save();
    }

    public function clear(): void
    {
        $this->data = [];
        $this->save();
    }

    public function filePath(): string
    {
        return $this->filePath;
    }
}
