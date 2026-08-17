<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shift\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Shift\Command\ForceCloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\ForceCloseShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\OpenShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\RecordCashDropHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Application\Shift\Command\RecordCashDrop;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\CashDropRecorded;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Shift\Repository\InMemoryShiftRepository;
use Dranzd\StorebunkPos\Infrastructure\Shift\Reservation\InMemoryShiftSlotReservation;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class RecordCashDropHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryShiftRepository $shiftRepository;
    private RecordCashDropHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->shiftRepository = new InMemoryShiftRepository($this->eventStore);
        $this->handler = new RecordCashDropHandler($this->shiftRepository);
    }

    public function test_records_a_cash_drop_on_an_open_shift(): void
    {
        $shiftId = $this->openShift();

        ($this->handler)(new RecordCashDrop($shiftId->toNative(), 2500, 'PHP'));

        $drops = array_values(array_filter(
            $this->eventStore->loadEvents($shiftId->toNative()),
            fn ($e) => $e instanceof CashDropRecorded
        ));

        $this->assertCount(1, $drops);
        $this->assertSame(2500, $drops[0]->getAmount()->toArray()['amount']);
    }

    public function test_refuses_a_cash_drop_on_a_closed_shift(): void
    {
        $shiftId = $this->openShift();
        $forceClose = new ForceCloseShiftHandler($this->shiftRepository, new InMemoryShiftSlotReservation());
        $forceClose(new ForceCloseShift($shiftId->toNative(), 'supervisor-1', 'end of day'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot record cash drop on a closed shift');

        ($this->handler)(new RecordCashDrop($shiftId->toNative(), 2500, 'PHP'));
    }

    private function openShift(): ShiftId
    {
        $shiftId = new ShiftId();
        $openHandler = new OpenShiftHandler($this->shiftRepository, new InMemoryShiftSlotReservation());
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
