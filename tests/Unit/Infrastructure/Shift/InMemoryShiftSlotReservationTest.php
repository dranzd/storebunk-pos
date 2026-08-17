<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Infrastructure\Shift;

use Dranzd\StorebunkPos\Infrastructure\Shift\Reservation\InMemoryShiftSlotReservation;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class InMemoryShiftSlotReservationTest extends TestCase
{
    // Terminal slots are asserted through TerminalId, which validates UUIDs.
    private const TERM_1 = '11111111-1111-4111-8111-111111111111';
    private const TERM_2 = '22222222-2222-4222-8222-222222222222';
    private const TERM_3 = '33333333-3333-4333-8333-333333333333';

    private InMemoryShiftSlotReservation $reservation;

    protected function setUp(): void
    {
        $this->reservation = new InMemoryShiftSlotReservation();
    }

    public function test_reserving_an_occupied_terminal_is_refused(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');

        $this->reservation->reserveForOpen(self::TERM_1, 'cash-2', 'shift-2');
    }

    public function test_reserving_for_a_busy_cashier_is_refused_without_taking_the_terminal(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');

        try {
            $this->reservation->reserveForOpen(self::TERM_2, 'cash-1', 'shift-2');
            $this->fail('Expected the busy-cashier refusal');
        } catch (InvariantViolationException) {
        }

        // All-or-nothing: the refused reservation must not have claimed the
        // free terminal slot as a side effect.
        $this->expectNotToPerformAssertions();
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-2', 'shift-3');
    }

    public function test_release_frees_both_slots(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->releaseShift('shift-1');

        $this->expectNotToPerformAssertions();

        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-2');
    }

    public function test_release_is_idempotent(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->releaseShift('shift-1');

        $this->expectNotToPerformAssertions();

        $this->reservation->releaseShift('shift-1');
    }

    public function test_transfer_moves_the_cashier_slot_and_reports_the_previous_holder(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');

        $previous = $this->reservation->transferCashier('shift-1', 'cash-2');

        $this->assertSame('cash-1', $previous);

        // The previous holder is free again; the new holder is not.
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-1', 'shift-2');
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');
        $this->reservation->reserveForOpen(self::TERM_3, 'cash-2', 'shift-3');
    }

    public function test_transfer_to_a_cashier_holding_another_shift_is_refused(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-2', 'shift-2');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already operates another open shift');

        $this->reservation->transferCashier('shift-1', 'cash-2');
    }

    public function test_transfer_to_the_current_holder_is_a_no_op(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');

        $this->assertNull($this->reservation->transferCashier('shift-1', 'cash-1'));
    }
}
