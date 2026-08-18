<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Infrastructure\Shift;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftAssigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftForceClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftOpened;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftUnassigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Shift\ReadModel\InMemoryShiftReadModel;
use PHPUnit\Framework\TestCase;

/**
 * The projection is what shift-slot reconciliation treats as the committed
 * authority, so a wrong `cashier_id` here becomes wrong slots there. These
 * cover the event orders that decide who "operates" a shift.
 */
final class InMemoryShiftReadModelTest extends TestCase
{
    private InMemoryShiftReadModel $readModel;
    private ShiftId $shiftId;
    private TerminalId $terminalId;
    private CashierId $opener;

    protected function setUp(): void
    {
        $this->readModel  = new InMemoryShiftReadModel();
        $this->shiftId    = new ShiftId();
        $this->terminalId = new TerminalId();
        $this->opener     = new CashierId();
    }

    public function test_the_opener_operates_a_freshly_opened_shift(): void
    {
        $this->open();

        $row = $this->readModel->getShift($this->shiftId->toNative());

        $this->assertSame($this->opener->toNative(), $row['cashier_id']);
        $this->assertSame($this->opener->toNative(), $row['opened_by']);
        $this->assertSame($this->terminalId->toNative(), $row['terminal_id']);
        $this->assertTrue($row['open']);
        $this->assertCount(1, $this->readModel->getOpenShifts());
    }

    public function test_assignment_moves_the_operator_and_reassignment_moves_it_again(): void
    {
        $first  = new CashierId();
        $second = new CashierId();
        $this->open();

        $this->readModel->onShiftAssigned(ShiftAssigned::occur($this->shiftId, $first, [], new \DateTimeImmutable()));
        $this->assertSame($first->toNative(), $this->readModel->getShift($this->shiftId->toNative())['cashier_id']);

        $this->readModel->onShiftAssigned(ShiftAssigned::occur($this->shiftId, $second, [], new \DateTimeImmutable()));
        $this->assertSame($second->toNative(), $this->readModel->getShift($this->shiftId->toNative())['cashier_id']);
        // The opener is remembered throughout — unassign hands the shift back.
        $this->assertSame($this->opener->toNative(), $this->readModel->getShift($this->shiftId->toNative())['opened_by']);
    }

    public function test_unassignment_returns_the_shift_to_its_opener(): void
    {
        $this->open();
        $this->readModel->onShiftAssigned(
            ShiftAssigned::occur($this->shiftId, new CashierId(), [], new \DateTimeImmutable())
        );

        $this->readModel->onShiftUnassigned(ShiftUnassigned::occur($this->shiftId, new \DateTimeImmutable()));

        $this->assertSame($this->opener->toNative(), $this->readModel->getShift($this->shiftId->toNative())['cashier_id']);
    }

    public function test_unassignment_without_a_prior_assignment_leaves_the_opener_operating(): void
    {
        $this->open();

        $this->readModel->onShiftUnassigned(ShiftUnassigned::occur($this->shiftId, new \DateTimeImmutable()));

        $this->assertSame($this->opener->toNative(), $this->readModel->getShift($this->shiftId->toNative())['cashier_id']);
    }

    public function test_a_closed_shift_leaves_the_open_set(): void
    {
        $this->open();

        $this->readModel->onShiftClosed(ShiftClosed::occur(
            $this->shiftId,
            $this->money(50000),
            $this->money(50000),
            $this->money(0),
            new \DateTimeImmutable()
        ));

        $this->assertSame([], $this->readModel->getOpenShifts());
        $this->assertFalse($this->readModel->getShift($this->shiftId->toNative())['open']);
    }

    public function test_a_force_closed_shift_leaves_the_open_set(): void
    {
        $this->open();

        $this->readModel->onShiftForceClosed(
            ShiftForceClosed::occur($this->shiftId, 'sup-1', 'end of day', new \DateTimeImmutable())
        );

        $this->assertSame([], $this->readModel->getOpenShifts());
    }

    public function test_a_stale_unassign_after_a_close_cannot_resurrect_the_shift(): void
    {
        // Reconciliation reads getOpenShifts(); a late-projected event for a
        // closed shift must not put it back in that set.
        $this->open();
        $this->readModel->onShiftForceClosed(
            ShiftForceClosed::occur($this->shiftId, 'sup-1', 'end of day', new \DateTimeImmutable())
        );

        $this->readModel->onShiftUnassigned(ShiftUnassigned::occur($this->shiftId, new \DateTimeImmutable()));

        $this->assertSame([], $this->readModel->getOpenShifts());
    }

    public function test_shifts_are_listed_per_terminal(): void
    {
        $this->open();
        $otherOnSameTerminal = new ShiftId();
        $this->readModel->onShiftOpened(ShiftOpened::occur(
            $otherOnSameTerminal,
            $this->terminalId,
            new BranchId(),
            new CashierId(),
            $this->money(50000),
            new \DateTimeImmutable()
        ));
        $this->readModel->onShiftOpened(ShiftOpened::occur(
            new ShiftId(),
            new TerminalId(),
            new BranchId(),
            new CashierId(),
            $this->money(50000),
            new \DateTimeImmutable()
        ));

        $this->assertCount(2, $this->readModel->getShiftsByTerminal($this->terminalId->toNative()));
        $this->assertCount(3, $this->readModel->getOpenShifts());
    }

    private function open(): void
    {
        $this->readModel->onShiftOpened(ShiftOpened::occur(
            $this->shiftId,
            $this->terminalId,
            new BranchId(),
            $this->opener,
            $this->money(50000),
            new \DateTimeImmutable()
        ));
    }

    private function money(int $amount): Money
    {
        return Money::fromArray(['amount' => $amount, 'currency' => 'PHP']);
    }
}
