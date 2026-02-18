<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

final class CliArgs
{
    /** @var array<string, string|bool> */
    private array $options = [];

    /** @var list<string> */
    private array $positional = [];

    public function __construct(array $argv)
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $arg = substr($arg, 2);
                if (str_contains($arg, '=')) {
                    [$key, $value] = explode('=', $arg, 2);
                    $this->options[$key] = $value;
                } else {
                    $this->options[$arg] = true;
                }
            } else {
                $this->positional[] = $arg;
            }
        }
    }

    public function get(string $name, string $default = ''): string
    {
        $value = $this->options[$name] ?? $default;
        return (string) $value;
    }

    public function getInt(string $name, int $default = 0): int
    {
        return isset($this->options[$name]) ? (int) $this->options[$name] : $default;
    }

    public function getBool(string $name): bool
    {
        return isset($this->options[$name]) && $this->options[$name] !== 'false';
    }

    public function has(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function require(string $name): string
    {
        if (!isset($this->options[$name]) || $this->options[$name] === '') {
            Output::error("Missing required option: --{$name}");
            exit(1);
        }
        return (string) $this->options[$name];
    }

    public function requireInt(string $name): int
    {
        $value = $this->require($name);
        if (!is_numeric($value)) {
            Output::error("Option --{$name} must be an integer");
            exit(1);
        }
        return (int) $value;
    }

    public function positional(int $index): string
    {
        return $this->positional[$index] ?? '';
    }
}
