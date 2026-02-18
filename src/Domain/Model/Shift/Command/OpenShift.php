<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class OpenShift extends AbstractCommand
{
    private ShiftId $shiftId;
    private TerminalId $terminalId;
    private BranchId $branchId;
    private CashierId $cashierId;
    private Money $openingCashAmount;

    public function __construct(
        ShiftId $shiftId,
        TerminalId $terminalId,
        BranchId $branchId,
        CashierId $cashierId,
        Money $openingCashAmount
    ) {
        $this->shiftId = $shiftId;
        $this->terminalId = $terminalId;
        $this->branchId = $branchId;
        $this->cashierId = $cashierId;
        $this->openingCashAmount = $openingCashAmount;

        parent::__construct(
            $shiftId->toNative(),
            self::expectedMessageName(),
            [
                'shift_id' => $shiftId->toNative(),
                'terminal_id' => $terminalId->toNative(),
                'branch_id' => $branchId->toNative(),
                'cashier_id' => $cashierId->toNative(),
                'opening_cash_amount' => $openingCashAmount->toArray(),
            ]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.open';
    }

    final public function shiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function branchId(): BranchId
    {
        return $this->branchId;
    }

    final public function cashierId(): CashierId
    {
        return $this->cashierId;
    }

    final public function openingCashAmount(): Money
    {
        return $this->openingCashAmount;
    }
}
