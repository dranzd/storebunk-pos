<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shift\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Shift\Command\ForceCloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\ForceCloseShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\OpenShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftForceClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Shift\Repository\InMemoryShiftRepository;
use Dranzd\StorebunkPos\Infrastructure\Shift\Reservation\InMemoryShiftSlotReservation;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class ForceCloseShiftHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryShiftRepository $shiftRepository;
    private InMemoryShiftSlotReservation $slotReservation;
    private ForceCloseShiftHandler $handler;

    protected function setUp(): void
    {
        $this->slotReservation = new InMemoryShiftSlotReservation();
        $this->eventStore = new InMemoryEventStore();
        $this->shiftRepository = new InMemoryShiftRepository($this->eventStore);
        $this->handler = new ForceCloseShiftHandler($this->shiftRepository, $this->slotReservation);
    }

    public function test_force_closes_an_open_shift_with_supervisor_and_reason(): void
    {
        $shiftId = $this->openShift();

        ($this->handler)(new ForceCloseShift($shiftId->toNative(), 'supervisor-1', 'drawer jammed'));

        $forceClosed = array_values(array_filter(
            $this->eventStore->loadEvents($shiftId->toNative()),
            fn ($e) => $e instanceof ShiftForceClosed
        ));

        $this->assertCount(1, $forceClosed);
        $this->assertSame('supervisor-1', $forceClosed[0]->getSupervisorId());
        $this->assertSame('drawer jammed', $forceClosed[0]->getReason());
    }

    public function test_refuses_to_force_close_a_shift_that_is_not_open(): void
    {
        $shiftId = $this->openShift();
        ($this->handler)(new ForceCloseShift($shiftId->toNative(), 'supervisor-1', 'drawer jammed'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot force close a shift that is not open');

        ($this->handler)(new ForceCloseShift($shiftId->toNative(), 'supervisor-1', 'again'));
    }

    private function openShift(): ShiftId
    {
        $shiftId = new ShiftId();
        $openHandler = new OpenShiftHandler($this->shiftRepository, $this->slotReservation);
        $openHandler(new OpenShift(
            $shiftId->toNative(),
            (new TerminalId())->toNative(),
            (new BranchId())->toNative(),
            (new CashierId())->toNative(),
            50000,
            'PHP'
        ));

        return $shiftId;
    }
}
