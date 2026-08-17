<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shift\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\AssignShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\OpenShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftAssigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Shift\Repository\InMemoryShiftRepository;
use Dranzd\StorebunkPos\Infrastructure\Shift\Reservation\InMemoryShiftSlotReservation;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
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

        $this->expectNotToPerformAssertions();

        // Re-issuing membership on the shift the cashier already operates is
        // the documented "replace membership" path — not a second shift.
        ($this->handler)(new AssignShift(
            $shiftId->toNative(),
            $cashier->toNative(),
            [(new CashierId())->toNative()]
        ));
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

    public function test_a_losing_assign_does_not_clobber_the_winning_assign(): void
    {
        // Controlled interleaving of two racing assigns: the loser transfers
        // the slot first, the winner's full assign commits DURING the loser's
        // failing store, then the loser compensates. Its compensation must be
        // a no-op — the winner's committed reservation stays.
        $opener  = new CashierId();
        $shiftId = $this->openShift($opener);
        $loserAssignee  = new CashierId();
        $winnerAssignee = new CashierId();

        $winnerHandler = $this->handler;
        $interleavingRepository = new class (
            $this->shiftRepository,
            static fn () => $winnerHandler(new AssignShift(
                $shiftId->toNative(),
                $winnerAssignee->toNative(),
                []
            ))
        ) implements ShiftRepositoryInterface {
            public bool $failNextStore = true;

            /**
             * @param callable(): void $onFailingStore
             */
            public function __construct(
                private readonly InMemoryShiftRepository $inner,
                private $onFailingStore
            ) {
            }

            public function load(ShiftId $shiftId): Shift
            {
                return $this->inner->load($shiftId);
            }

            public function store(Shift $shift, ?int $expectedVersion = null): void
            {
                if ($this->failNextStore) {
                    $this->failNextStore = false;
                    ($this->onFailingStore)();
                    throw new \RuntimeException('optimistic lock lost');
                }
                $this->inner->store($shift, $expectedVersion);
            }
        };
        $loserHandler = new AssignShiftHandler($interleavingRepository, $this->slotReservation);

        try {
            $loserHandler(new AssignShift($shiftId->toNative(), $loserAssignee->toNative(), []));
            $this->fail('Expected the losing store to throw');
        } catch (\RuntimeException) {
        }

        // The winner's assignee still operates the shift; the loser's
        // assignee and the opener are both free.
        $this->openShift($loserAssignee);
        $this->openShift($opener);
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already operates another open shift');
        ($this->handler)(new AssignShift($this->openShift()->toNative(), $winnerAssignee->toNative(), []));
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
