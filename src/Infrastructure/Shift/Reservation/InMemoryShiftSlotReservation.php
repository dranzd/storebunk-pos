<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Infrastructure\Shift\Reservation;

use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\MultiTerminalEnforcementService;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;

/**
 * Single-process reference implementation. PHP requests are single-threaded,
 * so plain array operations are atomic here; multi-process hosts need an
 * implementation with real shared-state atomicity (see the interface).
 * The occupancy RULES stay in MultiTerminalEnforcementService — this class
 * only adds slot state and the atomicity boundary.
 */
final class InMemoryShiftSlotReservation implements ShiftSlotReservationInterface
{
    /** @var array<string, string> terminalId => shiftId */
    private array $terminalSlots = [];

    /** @var array<string, string> cashierId => shiftId */
    private array $cashierSlots = [];

    public function __construct(
        private readonly MultiTerminalEnforcementService $multiTerminalEnforcement = new MultiTerminalEnforcementService()
    ) {
    }

    final public function reserveForOpen(string $terminalId, string $cashierId, string $shiftId): void
    {
        $this->multiTerminalEnforcement->assertTerminalHasNoOpenShift(
            TerminalId::fromNative($terminalId),
            $this->terminalSlots
        );
        $this->multiTerminalEnforcement->assertCashierHasNoOpenShift(
            $cashierId,
            $this->cashierSlots
        );

        $this->terminalSlots[$terminalId] = $shiftId;
        $this->cashierSlots[$cashierId]   = $shiftId;
    }

    final public function transferCashier(string $shiftId, string $newCashierId): ?string
    {
        $this->multiTerminalEnforcement->assertCashierFreeForShift(
            $newCashierId,
            $shiftId,
            $this->cashierSlots
        );

        $previousHolder = null;
        foreach ($this->cashierSlots as $cashierId => $heldShiftId) {
            if ($heldShiftId === $shiftId && $cashierId !== $newCashierId) {
                $previousHolder = $cashierId;
                unset($this->cashierSlots[$cashierId]);
            }
        }

        $this->cashierSlots[$newCashierId] = $shiftId;

        return $previousHolder;
    }

    final public function releaseShift(string $shiftId): void
    {
        $this->terminalSlots = array_filter(
            $this->terminalSlots,
            static fn(string $heldShiftId): bool => $heldShiftId !== $shiftId
        );
        $this->cashierSlots = array_filter(
            $this->cashierSlots,
            static fn(string $heldShiftId): bool => $heldShiftId !== $shiftId
        );
    }
}
