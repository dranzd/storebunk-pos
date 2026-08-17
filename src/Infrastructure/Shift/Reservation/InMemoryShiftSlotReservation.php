<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Infrastructure\Shift\Reservation;

use Dranzd\StorebunkPos\Domain\Service\ShiftSlotBook;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;

/**
 * Single-process reference implementation. PHP requests are single-threaded,
 * so plain array operations are atomic here; multi-process hosts need an
 * implementation with real shared-state atomicity (see the interface).
 * The bookkeeping — including the prepare/commit/abort protocol — lives in
 * ShiftSlotBook; this class only holds the state and the atomicity boundary.
 *
 * @phpstan-import-type SlotState from ShiftSlotBook
 */
final class InMemoryShiftSlotReservation implements ShiftSlotReservationInterface
{
    /** @var SlotState */
    private array $slots;

    public function __construct(
        private readonly ShiftSlotBook $slotBook = new ShiftSlotBook()
    ) {
        $this->slots = $this->slotBook->emptyState();
    }

    final public function reserveForOpen(string $terminalId, string $cashierId, string $shiftId): void
    {
        $this->slots = $this->slotBook->reserveForOpen($this->slots, $terminalId, $cashierId, $shiftId);
    }

    final public function prepareTransfer(string $shiftId, string $newCashierId): void
    {
        $this->slots = $this->slotBook->prepareTransfer($this->slots, $shiftId, $newCashierId);
    }

    final public function commitTransfer(string $shiftId, string $newCashierId): void
    {
        $this->slots = $this->slotBook->commitTransfer($this->slots, $shiftId, $newCashierId);
    }

    final public function abortTransfer(string $shiftId, string $newCashierId): void
    {
        $this->slots = $this->slotBook->abortTransfer($this->slots, $shiftId, $newCashierId);
    }

    final public function releaseShift(string $shiftId): void
    {
        $this->slots = $this->slotBook->releaseShift($this->slots, $shiftId);
    }

    final public function reconcile(array $openShiftsById): int
    {
        $reconciled  = $this->slotBook->stateFor($openShiftsById);
        $corrections = $this->slotBook->correctionCount($this->slots, $reconciled);
        $this->slots = $reconciled;

        return $corrections;
    }
}
