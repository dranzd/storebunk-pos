<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shift\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\AssignShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\OpenShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftAssigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Shift\Repository\InMemoryShiftRepository;
use Dranzd\StorebunkPos\Infrastructure\Shift\Reservation\InMemoryShiftSlotReservation;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use Dranzd\StorebunkPos\Tests\Stub\Repository\CallbackFailingShiftRepository;
use PHPUnit\Framework\TestCase;

final class AssignShiftHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryShiftRepository $shiftRepository;
    private InMemoryShiftSlotReservation $slotReservation;
    private AssignShiftHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore      = new InMemoryEventStore();
        $this->shiftRepository = new InMemoryShiftRepository($this->eventStore);
        $this->slotReservation = new InMemoryShiftSlotReservation();
        $this->handler         = new AssignShiftHandler($this->shiftRepository, $this->slotReservation);
    }

    public function test_assigns_shift_to_cashier_with_fallbacks(): void
    {
        $shiftId   = $this->openShift();
        $assignee  = new CashierId();
        $fallbackA = new CashierId();
        $fallbackB = new CashierId();

        ($this->handler)(new AssignShift(
            $shiftId->toNative(),
            $assignee->toNative(),
            [$fallbackA->toNative(), $fallbackB->toNative()]
        ));

        $assigned = array_values(array_filter(
            $this->eventStore->loadEvents($shiftId->toNative()),
            fn ($e) => $e instanceof ShiftAssigned
        ));

        $this->assertCount(1, $assigned);
        $this->assertSame($assignee->toNative(), $assigned[0]->getAssignee()->toNative());
        $this->assertCount(2, $assigned[0]->getFallbackCashiers());

        // Membership survives reconstitution from the event store.
        $shift = $this->shiftRepository->load($shiftId);
        $this->assertTrue($shift->assignee()->sameValueAs($assignee));
        $this->assertCount(2, $shift->fallbackCashiers());
    }

    public function test_rejects_more_than_three_fallbacks(): void
    {
        $shiftId = $this->openShift();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('at most 3 fallback cashiers');

        ($this->handler)(new AssignShift(
            $shiftId->toNative(),
            (new CashierId())->toNative(),
            [
                (new CashierId())->toNative(),
                (new CashierId())->toNative(),
                (new CashierId())->toNative(),
                (new CashierId())->toNative(),
            ]
        ));
    }

    public function test_refuses_assigning_a_cashier_who_operates_another_open_shift(): void
    {
        $busyCashier = new CashierId();
        $this->openShift($busyCashier);
        $otherShiftId = $this->openShift();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already operates another open shift');

        ($this->handler)(new AssignShift(
            $otherShiftId->toNative(),
            $busyCashier->toNative(),
            []
        ));
    }

    public function test_allows_reassignment_within_the_operated_shift(): void
    {
        $cashier = new CashierId();
        $shiftId = $this->openShift($cashier);

        // Re-issuing membership on the shift the cashier already operates is
        // the documented "replace membership" path — not a second shift.
        $firstFallback = new CashierId();
        ($this->handler)(new AssignShift(
            $shiftId->toNative(),
            $cashier->toNative(),
            [$firstFallback->toNative()]
        ));
        $replacementFallback = new CashierId();
        ($this->handler)(new AssignShift(
            $shiftId->toNative(),
            $cashier->toNative(),
            [$replacementFallback->toNative()]
        ));

        // Membership was REPLACED, not appended or dropped.
        $shift = $this->shiftRepository->load($shiftId);
        $this->assertTrue($shift->assignee()->sameValueAs($cashier));
        $this->assertCount(1, $shift->fallbackCashiers());
        $this->assertTrue($shift->fallbackCashiers()[0]->sameValueAs($replacementFallback));
    }

    public function test_assignment_frees_the_previous_operator_to_open_a_shift(): void
    {
        // Opener hands their shift to an assignee; the opener's slot moves
        // with the assignment, so the opener can open a new shift.
        $opener  = new CashierId();
        $shiftId = $this->openShift($opener);

        ($this->handler)(new AssignShift(
            $shiftId->toNative(),
            (new CashierId())->toNative(),
            []
        ));

        $this->expectNotToPerformAssertions();
        $this->openShift($opener);
    }

    public function test_the_opener_cannot_take_a_second_shift_while_an_assign_is_in_flight(): void
    {
        // The interleaving the prepare/commit protocol exists to close: the
        // opener tries to open another shift DURING a failing assign, i.e.
        // while their release is proposed but not committed. They must be
        // refused — otherwise the failed assign's rollback would leave shift
        // A naming an opener who now operates shift B.
        $opener   = new CashierId();
        $shiftId  = $this->openShift($opener);
        $assignee = new CashierId();

        $openerRefused = null;
        $repository    = new CallbackFailingShiftRepository(
            $this->shiftRepository,
            function () use ($opener, &$openerRefused): void {
                try {
                    $this->openShift($opener);
                } catch (InvariantViolationException $refusal) {
                    $openerRefused = $refusal;
                }
            },
            'optimistic lock lost'
        );
        $handler = new AssignShiftHandler($repository, $this->slotReservation);

        try {
            $handler(new AssignShift($shiftId->toNative(), $assignee->toNative(), []));
            $this->fail('Expected the failing store to throw');
        } catch (\RuntimeException $failure) {
            $this->assertSame('optimistic lock lost', $failure->getMessage());
        }

        $this->assertNotNull($openerRefused, 'The opener must stay held while the assign is in flight');
        $this->assertStringContainsString('already has an open shift', $openerRefused->getMessage());

        // After the rollback the opener still operates the shift the
        // aggregate says they operate, and the assignee is free.
        $this->openShift($assignee);
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');
        $this->openShift($opener);
    }

    public function test_a_second_assign_is_refused_while_one_is_in_flight(): void
    {
        // Two racing assigns on the same shift: the second one cannot slip in
        // between the first one's claim and its commit.
        $shiftId  = $this->openShift();
        $assignee = new CashierId();
        $rival    = new CashierId();

        $rivalRefused = null;
        $repository   = new CallbackFailingShiftRepository(
            $this->shiftRepository,
            function () use ($shiftId, $rival, &$rivalRefused): void {
                try {
                    ($this->handler)(new AssignShift($shiftId->toNative(), $rival->toNative(), []));
                } catch (InvariantViolationException $refusal) {
                    $rivalRefused = $refusal;
                }
            }
        );
        $handler = new AssignShiftHandler($repository, $this->slotReservation);

        try {
            $handler(new AssignShift($shiftId->toNative(), $assignee->toNative(), []));
            $this->fail('Expected the failing store to throw');
        } catch (\RuntimeException) {
        }

        $this->assertNotNull($rivalRefused);
        $this->assertStringContainsString('transfer in flight', $rivalRefused->getMessage());

        // Once the failed assign rolled back, the rival assign succeeds.
        ($this->handler)(new AssignShift($shiftId->toNative(), $rival->toNative(), []));
        $this->assertTrue($this->shiftRepository->load($shiftId)->assignee()->sameValueAs($rival));
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
}
