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

    public function test_reserving_an_already_claimed_shift_id_is_refused(): void
    {
        // Two racing opens sharing a shift id must not both claim — the
        // loser's release would otherwise drop the winner's slots too.
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already holds open-shift slots');

        $this->reservation->reserveForOpen(self::TERM_2, 'cash-2', 'shift-1');
    }

    public function test_transfer_for_a_shift_without_slots_is_refused(): void
    {
        // A stale command must not recreate a cashier slot after the shift
        // was closed and its slots released.
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->releaseShift('shift-1');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('holds no cashier slot');

        $this->reservation->transferCashier('shift-1', 'cash-2');
    }

    public function test_compensation_restores_the_previous_holder_when_nothing_moved_on(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->transferCashier('shift-1', 'cash-2');

        $this->reservation->compensateTransfer('shift-1', 'cash-1', 'cash-2');

        // cash-1 operates shift-1 again; cash-2 is free.
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-2', 'shift-2');
        $this->expectException(InvariantViolationException::class);
        $this->reservation->reserveForOpen(self::TERM_3, 'cash-1', 'shift-3');
    }

    public function test_compensation_is_a_no_op_when_a_newer_command_moved_the_slot(): void
    {
        // Stale command transferred to cash-2, then a NEWER command moved the
        // slot to cash-3 and committed. The stale compensation must not
        // overwrite that committed state.
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->transferCashier('shift-1', 'cash-2');
        $this->reservation->transferCashier('shift-1', 'cash-3');

        $this->reservation->compensateTransfer('shift-1', 'cash-1', 'cash-2');

        // cash-3 still operates shift-1; cash-1 and cash-2 are free.
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-1', 'shift-2');
        $this->reservation->reserveForOpen(self::TERM_3, 'cash-2', 'shift-3');
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already operates another open shift');
        $this->reservation->transferCashier('shift-2', 'cash-3');
    }

    public function test_compensation_never_steals_a_slot_the_target_since_acquired(): void
    {
        // While the stale command was failing, its previous holder opened a
        // NEW shift. Compensation must drop the stale claim, not clobber the
        // previous holder's new slot.
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->transferCashier('shift-1', 'cash-2');
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-1', 'shift-2');

        $this->reservation->compensateTransfer('shift-1', 'cash-1', 'cash-2');

        // cash-1 still operates shift-2 (not clobbered) and cash-2 is free.
        $this->reservation->reserveForOpen(self::TERM_3, 'cash-2', 'shift-3');
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already operates another open shift');
        $this->reservation->transferCashier('shift-3', 'cash-1');
    }
}
