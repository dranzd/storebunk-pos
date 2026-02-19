<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

final class MultiTerminalEnforcementService
{
    /**
     * Assert that the given terminal has no open shift.
     *
     * @param array<string, string> $openShiftsByTerminal terminalId => shiftId, sourced from the read model
     */
    public function assertTerminalHasNoOpenShift(TerminalId $terminalId, array $openShiftsByTerminal): void
    {
        if (isset($openShiftsByTerminal[$terminalId->toNative()])) {
            throw InvariantViolationException::withMessage(
                sprintf(
                    'Terminal "%s" already has an open shift',
                    $terminalId->toNative()
                )
            );
        }
    }

    /**
     * Assert that the given cashier has no open shift on any terminal.
     *
     * @param array<string, string> $activeTerminalByCashier cashierId => terminalId, sourced from the read model
     */
    public function assertCashierHasNoOpenShift(string $cashierId, array $activeTerminalByCashier): void
    {
        if (isset($activeTerminalByCashier[$cashierId])) {
            throw InvariantViolationException::withMessage(
                sprintf(
                    'Cashier "%s" already has an open shift on another terminal',
                    $cashierId
                )
            );
        }
    }

    /**
     * Assert that the given order belongs to the given terminal.
     *
     * @param array<string, string> $orderTerminalBinding orderId => terminalId, sourced from the read model
     */
    public function assertOrderBelongsToTerminal(
        OrderId $orderId,
        TerminalId $terminalId,
        array $orderTerminalBinding
    ): void {
        $boundTerminal = $orderTerminalBinding[$orderId->toNative()] ?? null;

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
}
