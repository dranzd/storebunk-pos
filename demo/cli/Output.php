<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

final class Output
{
    private const RESET  = "\033[0m";
    private const BOLD   = "\033[1m";
    private const GREEN  = "\033[32m";
    private const YELLOW = "\033[33m";
    private const CYAN   = "\033[36m";
    private const RED    = "\033[31m";
    private const GRAY   = "\033[90m";
    private const WHITE  = "\033[97m";

    public static function banner(string $title): void
    {
        $line = str_repeat('─', 60);
        echo self::CYAN . self::BOLD . "\n" . $line . "\n";
        echo "  " . strtoupper($title) . "\n";
        echo $line . self::RESET . "\n\n";
    }

    public static function section(string $title): void
    {
        echo self::YELLOW . self::BOLD . "\n▶ " . $title . self::RESET . "\n";
    }

    public static function field(string $label, string $value): void
    {
        $pad = str_pad($label, 18);
        echo "  " . self::GRAY . $pad . self::RESET . self::WHITE . $value . self::RESET . "\n";
    }

    public static function success(string $message): void
    {
        echo self::GREEN . self::BOLD . "✓ " . $message . self::RESET . "\n";
    }

    public static function info(string $message): void
    {
        echo self::CYAN . "  " . $message . self::RESET . "\n";
    }

    public static function warning(string $message): void
    {
        echo self::YELLOW . "⚠ " . $message . self::RESET . "\n";
    }

    public static function error(string $message): void
    {
        echo self::RED . self::BOLD . "ERROR: " . $message . self::RESET . "\n";
    }

    public static function domainError(string $message): void
    {
        echo self::RED . "Domain error: " . $message . self::RESET . "\n";
    }

    public static function concurrencyError(string $message): void
    {
        echo self::RED . "Concurrency conflict: " . $message . self::RESET . "\n";
    }

    public static function step(int $n, string $description): void
    {
        echo self::BOLD . self::CYAN . "\n[Step " . $n . "] " . self::RESET . $description . "\n";
    }

    public static function blank(): void
    {
        echo "\n";
    }

    public static function separator(): void
    {
        echo self::GRAY . "  " . str_repeat('·', 50) . self::RESET . "\n";
    }

    public static function usage(string $usage): void
    {
        echo self::YELLOW . "Usage: " . self::RESET . $usage . "\n";
    }

    public static function money(int $amountMinorUnits, string $currency = 'PHP'): string
    {
        return $currency . ' ' . number_format($amountMinorUnits / 100, 2);
    }

    public static function formatMoney(array $moneyArray): string
    {
        $amount   = $moneyArray['amount'] ?? 0;
        $currency = $moneyArray['currency'] ?? 'PHP';
        return self::money((int) $amount, (string) $currency);
    }
}
