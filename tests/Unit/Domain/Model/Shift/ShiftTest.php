<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Model\Shift;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\CashDropRecorded;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftForceClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftOpened;
use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class ShiftTest extends TestCase
{
    public function test_it_can_be_opened(): void
    {
        $shiftId = new ShiftId();
        $terminalId = new TerminalId();
        $branchId = new BranchId();
        $cashierId = new CashierId();
        $openingCash = Money::fromArray(['amount' => 10000, 'currency' => 'USD']);

        $shift = Shift::open($shiftId, $terminalId, $branchId, $cashierId, $openingCash);

        $events = $shift->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ShiftOpened::class, $events[0]);

        $event = $events[0];
        assert($event instanceof ShiftOpened);
        $this->assertTrue($event->shiftId()->sameValueAs($shiftId));
        $this->assertTrue($event->terminalId()->sameValueAs($terminalId));
        $this->assertTrue($event->branchId()->sameValueAs($branchId));
        $this->assertTrue($event->cashierId()->sameValueAs($cashierId));
        $this->assertInstanceOf(DateTimeImmutable::class, $event->openedAt());
    }

    public function test_it_can_be_closed(): void
    {
        $shift = $this->createOpenedShift();
        $shift->popRecordedEvents();

        $declaredCash = Money::fromArray(['amount' => 10000, 'currency' => 'USD']);
        $shift->close($declaredCash);

        $events = $shift->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ShiftClosed::class, $events[0]);

        $event = $events[0];
        assert($event instanceof ShiftClosed);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->closedAt());
    }

    public function test_it_cannot_close_if_not_open(): void
    {
        $shift = $this->createOpenedShift();
        $declaredCash = Money::fromArray(['amount' => 10000, 'currency' => 'USD']);
        $shift->close($declaredCash);
        $shift->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot close a shift that is not open');

        $shift->close($declaredCash);
    }

    public function test_it_can_be_force_closed(): void
    {
        $shift = $this->createOpenedShift();
        $shift->popRecordedEvents();

        $shift->forceClose('supervisor-123', 'Emergency closure');

        $events = $shift->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ShiftForceClosed::class, $events[0]);

        $event = $events[0];
        assert($event instanceof ShiftForceClosed);
        $this->assertSame('supervisor-123', $event->supervisorId());
        $this->assertSame('Emergency closure', $event->reason());
        $this->assertInstanceOf(DateTimeImmutable::class, $event->forceClosedAt());
    }

    public function test_it_can_record_cash_drop(): void
    {
        $shift = $this->createOpenedShift();
        $shift->popRecordedEvents();

        $dropAmount = Money::fromArray(['amount' => 5000, 'currency' => 'USD']);
        $shift->recordCashDrop($dropAmount);

        $events = $shift->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CashDropRecorded::class, $events[0]);

        $event = $events[0];
        assert($event instanceof CashDropRecorded);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->recordedAt());
    }

    public function test_it_cannot_record_cash_drop_on_closed_shift(): void
    {
        $shift = $this->createOpenedShift();
        $declaredCash = Money::fromArray(['amount' => 10000, 'currency' => 'USD']);
        $shift->close($declaredCash);
        $shift->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot record cash drop on a closed shift');

        $dropAmount = Money::fromArray(['amount' => 5000, 'currency' => 'USD']);
        $shift->recordCashDrop($dropAmount);
    }

    public function test_it_can_be_reconstituted_from_history(): void
    {
        $shiftId = new ShiftId();
        $originalShift = Shift::open(
            $shiftId,
            new TerminalId(),
            new BranchId(),
            new CashierId(),
            Money::fromArray(['amount' => 10000, 'currency' => 'USD'])
        );
        $originalShift->recordCashDrop(Money::fromArray(['amount' => 5000, 'currency' => 'USD']));
        $originalShift->close(Money::fromArray(['amount' => 5000, 'currency' => 'USD']));
        $events = $originalShift->popRecordedEvents();

        $shift = new Shift();
        $shift = $shift->reconstituteFromHistory($events);

        $this->assertInstanceOf(Shift::class, $shift);
        $this->assertSame($shiftId->toNative(), $shift->getAggregateRootUuid());
        $this->assertSame(3, $shift->getAggregateRootVersion());
        $this->assertEmpty($shift->popRecordedEvents());
    }

    private function createOpenedShift(): Shift
    {
        return Shift::open(
            new ShiftId(),
            new TerminalId(),
            new BranchId(),
            new CashierId(),
            Money::fromArray(['amount' => 10000, 'currency' => 'USD'])
        );
    }
}
