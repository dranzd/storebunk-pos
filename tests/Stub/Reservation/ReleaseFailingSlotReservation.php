<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Stub\Reservation;

use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;

/**
 * Slot reservation whose releaseShift() fails — the "the shift is persisted
 * but its slots are not" failure a storage outage produces between the two
 * stores. Everything else behaves like the wrapped reservation.
 */
final class ReleaseFailingSlotReservation implements ShiftSlotReservationInterface
{
    public function __construct(
        private readonly ShiftSlotReservationInterface $inner,
        private readonly string $failureMessage = 'slot store unavailable'
    ) {
    }

    public function reserveForOpen(string $terminalId, string $cashierId, string $shiftId): void
    {
        $this->inner->reserveForOpen($terminalId, $cashierId, $shiftId);
    }

    public function prepareTransfer(string $shiftId, string $newCashierId): void
    {
        $this->inner->prepareTransfer($shiftId, $newCashierId);
    }

    public function commitTransfer(string $shiftId, string $newCashierId): void
    {
        $this->inner->commitTransfer($shiftId, $newCashierId);
    }

    public function abortTransfer(string $shiftId, string $newCashierId): void
    {
        $this->inner->abortTransfer($shiftId, $newCashierId);
    }

    public function releaseShift(string $shiftId): void
    {
        throw new \RuntimeException($this->failureMessage);
    }

    public function reconcile(array $openShiftsById): int
    {
        return $this->inner->reconcile($openShiftsById);
    }
}
