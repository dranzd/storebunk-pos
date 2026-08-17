<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Infrastructure\Shift\ReadModel;

use Dranzd\StorebunkPos\Application\Shift\ReadModel\ShiftReadModelInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftAssigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftForceClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftOpened;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftUnassigned;

/**
 * Event-projected shift read model. The host wires the on* projectors to its
 * event flow (replay on bootstrap, then per stored event). Query state only —
 * concurrency-authoritative occupancy lives in ShiftSlotReservationInterface.
 */
final class InMemoryShiftReadModel implements ShiftReadModelInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $shifts = [];

    final public function onShiftOpened(ShiftOpened $event): void
    {
        $this->shifts[$event->getShiftId()->toNative()] = [
            'shift_id'    => $event->getShiftId()->toNative(),
            'terminal_id' => $event->getTerminalId()->toNative(),
            'branch_id'   => $event->getBranchId()->toNative(),
            // The opener operates the shift until an explicit assignment
            // hands it to someone else; unassign hands it back.
            'cashier_id'  => $event->getCashierId()->toNative(),
            'opened_by'   => $event->getCashierId()->toNative(),
            'open'        => true,
            'opened_at'   => $event->getOpenedAt(),
            'closed_at'   => null,
        ];
    }

    final public function onShiftAssigned(ShiftAssigned $event): void
    {
        $shiftId = $event->getShiftId()->toNative();
        if (isset($this->shifts[$shiftId])) {
            $this->shifts[$shiftId]['cashier_id'] = $event->getAssignee()->toNative();
        }
    }

    final public function onShiftUnassigned(ShiftUnassigned $event): void
    {
        $shiftId = $event->getShiftId()->toNative();
        if (isset($this->shifts[$shiftId])) {
            $this->shifts[$shiftId]['cashier_id'] = $this->shifts[$shiftId]['opened_by'];
        }
    }

    final public function onShiftClosed(ShiftClosed $event): void
    {
        $this->markClosed($event->getShiftId()->toNative(), $event->getClosedAt());
    }

    final public function onShiftForceClosed(ShiftForceClosed $event): void
    {
        $this->markClosed($event->getShiftId()->toNative(), $event->occurredAt());
    }

    final public function getShift(string $shiftId): ?array
    {
        return $this->shifts[$shiftId] ?? null;
    }

    final public function getOpenShifts(): array
    {
        return array_values(
            array_filter($this->shifts, fn(array $shift) => $shift['open'])
        );
    }

    final public function getShiftsByTerminal(string $terminalId): array
    {
        return array_values(
            array_filter($this->shifts, fn(array $shift) => $shift['terminal_id'] === $terminalId)
        );
    }

    private function markClosed(string $shiftId, \DateTimeImmutable $closedAt): void
    {
        if (isset($this->shifts[$shiftId])) {
            $this->shifts[$shiftId]['open']      = false;
            $this->shifts[$shiftId]['closed_at'] = $closedAt;
        }
    }
}
