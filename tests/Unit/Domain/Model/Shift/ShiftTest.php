<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Model\Shift;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\CashDropRecorded;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftAssigned;
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
        $this->assertTrue($event->getShiftId()->sameValueAs($shiftId));
        $this->assertTrue($event->getTerminalId()->sameValueAs($terminalId));
        $this->assertTrue($event->getBranchId()->sameValueAs($branchId));
        $this->assertTrue($event->getCashierId()->sameValueAs($cashierId));
        $this->assertInstanceOf(DateTimeImmutable::class, $event->getOpenedAt());
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
        $this->assertInstanceOf(DateTimeImmutable::class, $event->getClosedAt());
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
        $this->assertSame('supervisor-123', $event->getSupervisorId());
        $this->assertSame('Emergency closure', $event->getReason());
        $this->assertInstanceOf(DateTimeImmutable::class, $event->getForceClosedAt());
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
        $this->assertInstanceOf(DateTimeImmutable::class, $event->getRecordedAt());
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

    public function test_a_freshly_opened_shift_is_unassigned_and_open(): void
    {
        $shift = $this->createOpenedShift();

        $this->assertFalse($shift->isAssigned());
        $this->assertNull($shift->assignee());
        $this->assertSame([], $shift->fallbackCashiers());
    }

    public function test_it_can_be_assigned_to_a_cashier_with_fallbacks(): void
    {
        $shift = $this->createOpenedShift();
        $shift->popRecordedEvents();

        $assignee  = new CashierId();
        $fallbackA = new CashierId();
        $fallbackB = new CashierId();

        $shift->assign($assignee, [$fallbackA, $fallbackB]);

        $events = $shift->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ShiftAssigned::class, $events[0]);

        $event = $events[0];
        assert($event instanceof ShiftAssigned);
        $this->assertTrue($event->getAssignee()->sameValueAs($assignee));
        $this->assertCount(2, $event->getFallbackCashiers());

        $this->assertTrue($shift->isAssigned());
        $this->assertTrue($shift->assignee()->sameValueAs($assignee));
        $this->assertCount(2, $shift->fallbackCashiers());
    }

    public function test_it_can_be_assigned_with_no_fallbacks(): void
    {
        $shift = $this->createOpenedShift();
        $shift->popRecordedEvents();

        $shift->assign(new CashierId(), []);

        $this->assertTrue($shift->isAssigned());
        $this->assertSame([], $shift->fallbackCashiers());
    }

    public function test_reassigning_replaces_the_previous_membership(): void
    {
        $shift = $this->createOpenedShift();
        $shift->assign(new CashierId(), [new CashierId()]);
        $shift->popRecordedEvents();

        $newAssignee = new CashierId();
        $shift->assign($newAssignee, []);

        $this->assertTrue($shift->assignee()->sameValueAs($newAssignee));
        $this->assertSame([], $shift->fallbackCashiers());
    }

    public function test_it_rejects_more_than_three_fallback_cashiers(): void
    {
        $shift = $this->createOpenedShift();
        $shift->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('at most 3 fallback cashiers');

        $shift->assign(new CashierId(), [new CashierId(), new CashierId(), new CashierId(), new CashierId()]);
    }

    public function test_it_rejects_assignee_appearing_as_a_fallback(): void
    {
        $shift = $this->createOpenedShift();
        $shift->popRecordedEvents();

        $assignee = new CashierId();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Assignee cannot also be a fallback cashier');

        $shift->assign($assignee, [$assignee]);
    }

    public function test_it_rejects_duplicate_fallback_cashiers(): void
    {
        $shift = $this->createOpenedShift();
        $shift->popRecordedEvents();

        $fallback = new CashierId();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Fallback cashiers must be distinct');

        $shift->assign(new CashierId(), [$fallback, $fallback]);
    }

    public function test_it_cannot_be_assigned_when_not_open(): void
    {
        $shift = $this->createOpenedShift();
        $shift->close(Money::fromArray(['amount' => 10000, 'currency' => 'USD']));
        $shift->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot assign a shift that is not open');

        $shift->assign(new CashierId(), []);
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
        $assignee = new CashierId();
        $originalShift->assign($assignee, [new CashierId()]);
        $originalShift->recordCashDrop(Money::fromArray(['amount' => 5000, 'currency' => 'USD']));
        $originalShift->close(Money::fromArray(['amount' => 5000, 'currency' => 'USD']));
        $events = $originalShift->popRecordedEvents();

        $shift = new Shift();
        $shift = $shift->reconstituteFromHistory($events);

        $this->assertInstanceOf(Shift::class, $shift);
        $this->assertSame($shiftId->toNative(), $shift->getAggregateRootUuid());
        $this->assertSame(4, $shift->getAggregateRootVersion());
        $this->assertTrue($shift->assignee()->sameValueAs($assignee));
        $this->assertCount(1, $shift->fallbackCashiers());
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
