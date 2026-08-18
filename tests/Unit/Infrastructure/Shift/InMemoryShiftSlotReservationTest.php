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

    public function test_a_committed_transfer_moves_the_cashier_slot(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');

        $this->reservation->prepareTransfer('shift-1', 'cash-2');
        $this->reservation->commitTransfer('shift-1', 'cash-2');

        // Only once committed is the previous holder free again.
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-1', 'shift-2');
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');
        $this->reservation->reserveForOpen(self::TERM_3, 'cash-2', 'shift-3');
    }

    public function test_an_in_flight_transfer_keeps_the_outgoing_cashier_busy(): void
    {
        // The heart of the prepare/commit protocol: while the aggregate
        // change is unpersisted, BOTH cashiers are held, so neither can pick
        // up another open shift on the strength of a change that may fail.
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->prepareTransfer('shift-1', 'cash-2');

        try {
            $this->reservation->reserveForOpen(self::TERM_2, 'cash-2', 'shift-2');
            $this->fail('Expected the incoming cashier to be held');
        } catch (InvariantViolationException) {
        }

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-1', 'shift-2');
    }

    public function test_transfer_to_a_cashier_holding_another_shift_is_refused(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-2', 'shift-2');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already operates another open shift');

        $this->reservation->prepareTransfer('shift-1', 'cash-2');
    }

    public function test_transfer_to_the_current_holder_is_a_no_op(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');

        $this->expectNotToPerformAssertions();

        $this->reservation->prepareTransfer('shift-1', 'cash-1');
        $this->reservation->commitTransfer('shift-1', 'cash-1');
        $this->reservation->abortTransfer('shift-1', 'cash-1');
    }

    public function test_a_second_transfer_of_the_same_shift_is_refused_while_one_is_in_flight(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->prepareTransfer('shift-1', 'cash-2');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has a cashier transfer in flight');

        $this->reservation->prepareTransfer('shift-1', 'cash-3');
    }

    public function test_a_transfer_back_to_the_current_holder_is_refused_while_one_is_in_flight(): void
    {
        // The composition the earlier rounds missed: the second command's
        // target IS the committed holder, so the "already the holder" no-op
        // would let it through, persist its own change, and then be silently
        // overwritten when the in-flight transfer commits — slots naming one
        // cashier while the events name another.
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->prepareTransfer('shift-1', 'cash-2');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has a cashier transfer in flight');

        $this->reservation->prepareTransfer('shift-1', 'cash-1');
    }

    public function test_reconcile_refuses_a_history_where_two_open_shifts_share_a_cashier(): void
    {
        // Reconciliation is the corruption-surfacing tool: silently keeping
        // the last shift would leave the other one slotless forever.
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('held by two open shifts');

        $this->reservation->reconcile([
            'shift-1' => ['terminal_id' => self::TERM_1, 'cashier_id' => 'cash-1'],
            'shift-2' => ['terminal_id' => self::TERM_2, 'cashier_id' => 'cash-1'],
        ]);
    }

    public function test_reconcile_refuses_a_history_where_two_open_shifts_share_a_terminal(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('held by two open shifts');

        $this->reservation->reconcile([
            'shift-1' => ['terminal_id' => self::TERM_1, 'cashier_id' => 'cash-1'],
            'shift-2' => ['terminal_id' => self::TERM_1, 'cashier_id' => 'cash-2'],
        ]);
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

        $this->reservation->prepareTransfer('shift-1', 'cash-2');
    }

    public function test_aborting_leaves_the_shift_exactly_as_it_was(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->prepareTransfer('shift-1', 'cash-2');

        $this->reservation->abortTransfer('shift-1', 'cash-2');

        // cash-1 still operates shift-1 (they never gave the slot up) and
        // cash-2 is free again.
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-2', 'shift-2');
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');
        $this->reservation->reserveForOpen(self::TERM_3, 'cash-1', 'shift-3');
    }

    public function test_an_abort_cannot_be_outrun_by_the_outgoing_cashier(): void
    {
        // The failure mode the prepare/commit protocol exists to close: while
        // the transfer was in flight, the outgoing cashier tried to open
        // another shift. They are refused, so the abort restores a state that
        // still matches the (unchanged) aggregate.
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->prepareTransfer('shift-1', 'cash-2');

        try {
            $this->reservation->reserveForOpen(self::TERM_2, 'cash-1', 'shift-2');
            $this->fail('Expected the outgoing cashier to still be held');
        } catch (InvariantViolationException) {
        }

        $this->reservation->abortTransfer('shift-1', 'cash-2');

        // shift-1 is still operated by cash-1 — no slotless open shift.
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already operates another open shift');
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-3', 'shift-2');
        $this->reservation->prepareTransfer('shift-2', 'cash-1');
    }

    public function test_commit_and_abort_are_no_ops_without_a_matching_preparation(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');

        $this->reservation->commitTransfer('shift-1', 'cash-2');
        $this->reservation->abortTransfer('shift-1', 'cash-2');

        // Nothing moved: cash-1 still holds shift-1, cash-2 is still free.
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-2', 'shift-2');
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');
        $this->reservation->reserveForOpen(self::TERM_3, 'cash-1', 'shift-3');
    }

    public function test_reconcile_drops_slots_with_no_open_shift_behind_them(): void
    {
        // A shift whose store failed after its slots were claimed, plus a
        // transfer abandoned mid-flight: both block until reconciliation.
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-2', 'shift-2');
        $this->reservation->prepareTransfer('shift-2', 'cash-3');

        $corrections = $this->reservation->reconcile([
            'shift-1' => ['terminal_id' => self::TERM_1, 'cashier_id' => 'cash-1'],
        ]);

        // shift-2's terminal slot, its cashier slot and the in-flight claim.
        $this->assertSame(3, $corrections);

        // The freed terminal and cashier can be claimed again.
        $this->reservation->reserveForOpen(self::TERM_2, 'cash-2', 'shift-3');
    }

    public function test_reconcile_reports_nothing_when_the_slots_already_match(): void
    {
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');

        $this->assertSame(0, $this->reservation->reconcile([
            'shift-1' => ['terminal_id' => self::TERM_1, 'cashier_id' => 'cash-1'],
        ]));
    }

    public function test_reconcile_restores_slots_a_committed_shift_should_hold(): void
    {
        // The mirror case: a shift that IS open but whose slots were lost.
        $corrections = $this->reservation->reconcile([
            'shift-1' => ['terminal_id' => self::TERM_1, 'cashier_id' => 'cash-1'],
        ]);

        $this->assertSame(2, $corrections);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');
        $this->reservation->reserveForOpen(self::TERM_1, 'cash-2', 'shift-2');
    }
}
