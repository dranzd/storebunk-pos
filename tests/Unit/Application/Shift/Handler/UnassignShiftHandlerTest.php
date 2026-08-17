<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shift\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\AssignShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\OpenShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\UnassignShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Application\Shift\Command\UnassignShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftUnassigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Shift\Repository\InMemoryShiftRepository;
use Dranzd\StorebunkPos\Infrastructure\Shift\Reservation\InMemoryShiftSlotReservation;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class UnassignShiftHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryShiftRepository $shiftRepository;
    private InMemoryShiftSlotReservation $slotReservation;
    private UnassignShiftHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore      = new InMemoryEventStore();
        $this->shiftRepository = new InMemoryShiftRepository($this->eventStore);
        $this->slotReservation = new InMemoryShiftSlotReservation();
        $this->handler         = new UnassignShiftHandler($this->shiftRepository, $this->slotReservation);
    }

    public function test_clears_membership_back_to_open(): void
    {
        $shiftId = $this->openAssignedShift();

        ($this->handler)(new UnassignShift($shiftId->toNative()));

        $unassigned = array_values(array_filter(
            $this->eventStore->loadEvents($shiftId->toNative()),
            fn ($e) => $e instanceof ShiftUnassigned
        ));
        $this->assertCount(1, $unassigned);

        $shift = $this->shiftRepository->load($shiftId);
        $this->assertFalse($shift->isAssigned());
        $this->assertNull($shift->assignee());
        $this->assertSame([], $shift->fallbackCashiers());
    }

    public function test_rejects_unassign_when_not_assigned(): void
    {
        $shiftId = $this->openShift();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Shift is not assigned');

        ($this->handler)(new UnassignShift($shiftId->toNative()));
    }

    public function test_refuses_unassign_when_the_opener_operates_another_open_shift(): void
    {
        // Opener opens shift A, hands it to an assignee, then opens shift B.
        // Unassigning A would give the opener two open shifts — refused.
        $opener = new CashierId();
        $shiftA = $this->openAssignedShift($opener);
        $this->openShift($opener);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already operates another open shift');

        ($this->handler)(new UnassignShift($shiftA->toNative()));
    }

    private function openShift(?CashierId $cashierId = null): ShiftId
    {
        $shiftId = new ShiftId();
        $openHandler = new OpenShiftHandler($this->shiftRepository, $this->slotReservation);
        $openHandler(new OpenShift(
            $shiftId->toNative(),
            (new TerminalId())->toNative(),
            (new BranchId())->toNative(),
            ($cashierId ?? new CashierId())->toNative(),
            50000,
            'PHP'
        ));

        return $shiftId;
    }

    private function openAssignedShift(?CashierId $opener = null): ShiftId
    {
        $shiftId = $this->openShift($opener);
        $assignHandler = new AssignShiftHandler($this->shiftRepository, $this->slotReservation);
        $assignHandler(new AssignShift(
            $shiftId->toNative(),
            (new CashierId())->toNative(),
            [(new CashierId())->toNative()]
        ));

        return $shiftId;
    }
}
