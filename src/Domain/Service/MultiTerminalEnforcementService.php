<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

final class MultiTerminalEnforcementService
{
    /** @var array<string, string> terminalId => shiftId */
    private array $openShiftsByTerminal = [];

    /** @var array<string, string> cashierId => terminalId */
    private array $activeTerminalByCashier = [];

    /** @var array<string, string> orderId => terminalId */
    private array $orderTerminalBinding = [];

    public function assertTerminalHasNoOpenShift(TerminalId $terminalId): void
    {
        if (isset($this->openShiftsByTerminal[$terminalId->toNative()])) {
            throw InvariantViolationException::withMessage(
                sprintf(
                    'Terminal "%s" already has an open shift',
                    $terminalId->toNative()
                )
            );
        }
    }

    public function assertCashierHasNoOpenShift(string $cashierId): void
    {
        if (isset($this->activeTerminalByCashier[$cashierId])) {
            throw InvariantViolationException::withMessage(
                sprintf(
                    'Cashier "%s" already has an open shift on another terminal',
                    $cashierId
                )
            );
        }
    }

    public function assertOrderBelongsToTerminal(OrderId $orderId, TerminalId $terminalId): void
    {
        $boundTerminal = $this->orderTerminalBinding[$orderId->toNative()] ?? null;

        if ($boundTerminal === null) {
            return;
        }

        if ($boundTerminal !== $terminalId->toNative()) {
            throw InvariantViolationException::withMessage(
                sprintf(
                    'Order "%s" is bound to terminal "%s" and cannot be accessed from terminal "%s"',
                    $orderId->toNative(),
                    $boundTerminal,
                    $terminalId->toNative()
                )
            );
        }
    }

    public function registerOpenShift(TerminalId $terminalId, ShiftId $shiftId, string $cashierId): void
    {
        $this->openShiftsByTerminal[$terminalId->toNative()] = $shiftId->toNative();
        $this->activeTerminalByCashier[$cashierId] = $terminalId->toNative();
    }

    public function unregisterOpenShift(TerminalId $terminalId, string $cashierId): void
    {
        unset($this->openShiftsByTerminal[$terminalId->toNative()]);
        unset($this->activeTerminalByCashier[$cashierId]);
    }

    public function bindOrderToTerminal(OrderId $orderId, TerminalId $terminalId): void
    {
        $this->orderTerminalBinding[$orderId->toNative()] = $terminalId->toNative();
    }

    public function unbindOrder(OrderId $orderId): void
    {
        unset($this->orderTerminalBinding[$orderId->toNative()]);
    }
}
