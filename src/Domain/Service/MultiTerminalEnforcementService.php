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
     * Assert that the given cashier is free to operate the given shift: they
     * must not currently operate a DIFFERENT open shift. Re-assigning a
     * cashier within the shift they already operate is allowed.
     *
     * @param array<string, string> $openShiftByCashier cashierId => shiftId, sourced from the read model
     */
    public function assertCashierFreeForShift(string $cashierId, string $shiftId, array $openShiftByCashier): void
    {
        $currentShift = $openShiftByCashier[$cashierId] ?? null;

        if ($currentShift !== null && $currentShift !== $shiftId) {
            throw InvariantViolationException::withMessage(
                sprintf(
                    'Cashier "%s" already operates another open shift',
                    $cashierId
                )
            );
        }
    }

    /**
     * Assert that the given order belongs to the given terminal.
     *
     * NOT called by this library, and that is deliberate — not an oversight.
     * Every command here that names an order checks it against its session's
     * own lists (parked, inactive, pending-sync) or acts on the session's
     * active order without taking an id at all, and a session is bound to one
     * terminal when it starts. So the binding is already structural: an order
     * can only be reached through the session that holds it. Adding a lookup
     * table for it would create a second home for the rule that could
     * disagree with the aggregate — the failure issue 8003 exists about.
     * Pinned by OrderTerminalBindingTest.
     *
     * It is here for HOSTS that address orders outside a session — an
     * endpoint taking an order id plus the caller's terminal, say — where
     * that structural scoping does not apply and the check must be made
     * explicitly.
     *
     * @param array<string, string> $orderTerminalBinding orderId => terminalId, as the host records it
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
