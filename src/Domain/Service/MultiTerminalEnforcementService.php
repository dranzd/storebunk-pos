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
     * An order already held by a session cannot be REACHED from another one:
     * resume, reactivate and sync each check the id against that session's
     * own parked / inactive / pending-sync list, and complete/cancel take no
     * id at all. A session is bound to one terminal at start, so that scoping
     * is what keeps an order on its own terminal, and a lookup table for it
     * would be a second home for the rule that could drift from the
     * aggregate — the failure issue 8003 exists about. Pinned by
     * OrderTerminalBindingTest.
     *
     * The gap it does NOT close is CLAIMING an id: `StartNewOrder` takes one
     * from the caller, and the session can only refuse ids it has already
     * used itself. Two sessions handed the same id is a host concern — order
     * ids belong to the Ordering context — which is exactly what this method
     * is here for. Call it from a host that lets a caller name an order.
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
