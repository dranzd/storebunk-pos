<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class OpenShift extends AbstractCommand
{
    private function __construct(
        private readonly string $shiftId,
        private readonly string $terminalId,
        private readonly string $branchId,
        private readonly string $cashierId,
        private readonly int $openingCashAmount,
        private readonly string $currency
    ) {
        parent::__construct(
            $this->shiftId,
            self::expectedMessageName(),
            [
                'shift_id' => $this->shiftId,
                'terminal_id' => $this->terminalId,
                'branch_id' => $this->branchId,
                'cashier_id' => $this->cashierId,
                'opening_cash_amount' => [
                    'amount' => $this->openingCashAmount,
                    'currency' => $this->currency,
                ],
            ]
        );
    }

    final public static function forCashier(
        string $shiftId,
        string $terminalId,
        string $branchId,
        string $cashierId,
        int $openingCashAmount,
        string $currency
    ): self {
        return new self($shiftId, $terminalId, $branchId, $cashierId, $openingCashAmount, $currency);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.open';
    }

    final public function shiftId(): ShiftId
    {
        return ShiftId::fromNative($this->shiftId);
    }

    final public function terminalId(): TerminalId
    {
        return TerminalId::fromNative($this->terminalId);
    }

    final public function branchId(): BranchId
    {
        return BranchId::fromNative($this->branchId);
    }

    final public function cashierId(): CashierId
    {
        return CashierId::fromNative($this->cashierId);
    }

    final public function openingCashAmount(): Money
    {
        return Money::fromArray(['amount' => $this->openingCashAmount, 'currency' => $this->currency]);
    }
}
