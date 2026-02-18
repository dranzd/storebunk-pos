<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\Domain\ValueObject\Native\Number\Integer as IntegerVO;
use Dranzd\Common\Domain\ValueObject\Native\String\Literal as StringVO;

final class Utils
{
    /**
     * Create a Money value object from minor units (e.g., cents).
     */
    public static function money(int $amountMinorUnits, string $currency = 'PHP'): Money
    {
        return Money::fromArray([
            'amount' => $amountMinorUnits,
            'currency' => $currency
        ]);
    }

    /**
     * Format money for display.
     */
    public static function formatMoney(Money $money): string
    {
        $array = $money->toArray();
        $amount = $array['amount'] ?? 0;
        $currency = $array['currency'] ?? 'PHP';
        return $currency . ' ' . number_format($amount / 100, 2);
    }

    /**
     * Format money array for display.
     */
    public static function formatMoneyArray(array $moneyArray): string
    {
        $amount = $moneyArray['amount'] ?? 0;
        $currency = $moneyArray['currency'] ?? 'PHP';
        return $currency . ' ' . number_format($amount / 100, 2);
    }
}
