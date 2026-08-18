<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shift\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Shift\Command\CloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\ForceCloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\CloseShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\ForceCloseShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\OpenShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\ShiftClosePolicy;
use Dranzd\StorebunkPos\Infrastructure\PosSession\ReadModel\InMemoryPosSessionReadModel;
use Dranzd\StorebunkPos\Infrastructure\Shift\Repository\InMemoryShiftRepository;
use Dranzd\StorebunkPos\Infrastructure\Shift\Reservation\InMemoryShiftSlotReservation;
use Dranzd\StorebunkPos\Shared\Exception\ConcurrencyException;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use Dranzd\StorebunkPos\Tests\Stub\Repository\InterleavingShiftRepository;
use PHPUnit\Framework\TestCase;

final class OpenShiftHandlerTest extends TestCase
{
    private InMemoryShiftRepository $shiftRepository;
    private InMemoryShiftSlotReservation $slotReservation;
    private OpenShiftHandler $handler;

    protected function setUp(): void
    {
        $this->shiftRepository = new InMemoryShiftRepository(new InMemoryEventStore());
        $this->slotReservation = new InMemoryShiftSlotReservation();
        $this->handler = new OpenShiftHandler($this->shiftRepository, $this->slotReservation);
    }

    public function test_opens_a_shift_on_a_free_terminal(): void
    {
        $shiftId = new ShiftId();

        ($this->handler)($this->openShiftCommand($shiftId, new TerminalId(), new CashierId()));

        $shift = $this->shiftRepository->load($shiftId);
        $this->assertSame($shiftId->toNative(), $shift->getAggregateRootUuid());
    }

    public function test_refuses_a_second_shift_on_the_same_terminal(): void
    {
        $terminalId = new TerminalId();
        ($this->handler)($this->openShiftCommand(new ShiftId(), $terminalId, new CashierId()));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');

        ($this->handler)($this->openShiftCommand(new ShiftId(), $terminalId, new CashierId()));
    }

    public function test_refuses_a_second_shift_for_the_same_cashier_on_another_terminal(): void
    {
        $cashierId = new CashierId();
        ($this->handler)($this->openShiftCommand(new ShiftId(), new TerminalId(), $cashierId));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift on another terminal');

        ($this->handler)($this->openShiftCommand(new ShiftId(), new TerminalId(), $cashierId));
    }

    public function test_allows_reopening_after_the_previous_shift_closed(): void
    {
        $terminalId = new TerminalId();
        $cashierId  = new CashierId();
        $firstShiftId = new ShiftId();
        ($this->handler)($this->openShiftCommand($firstShiftId, $terminalId, $cashierId));

        $closeHandler = new CloseShiftHandler(
            $this->shiftRepository,
            new ShiftClosePolicy(),
            new InMemoryPosSessionReadModel(),
            $this->slotReservation
        );
        $closeHandler(new CloseShift($firstShiftId->toNative(), 50000, 'PHP'));

        $this->expectNotToPerformAssertions();

        ($this->handler)($this->openShiftCommand(new ShiftId(), $terminalId, $cashierId));
    }

    public function test_allows_reopening_after_the_previous_shift_was_force_closed(): void
    {
        $terminalId = new TerminalId();
        $firstShiftId = new ShiftId();
        ($this->handler)($this->openShiftCommand($firstShiftId, $terminalId, new CashierId()));

        $forceCloseHandler = new ForceCloseShiftHandler($this->shiftRepository, $this->slotReservation);
        $forceCloseHandler(new ForceCloseShift($firstShiftId->toNative(), 'supervisor-1', 'drawer jammed'));

        $this->expectNotToPerformAssertions();

        ($this->handler)($this->openShiftCommand(new ShiftId(), $terminalId, new CashierId()));
    }

    public function test_different_terminals_and_cashiers_open_concurrently(): void
    {
        $firstShift  = new ShiftId();
        $secondShift = new ShiftId();
        ($this->handler)($this->openShiftCommand($firstShift, new TerminalId(), new CashierId()));
        ($this->handler)($this->openShiftCommand($secondShift, new TerminalId(), new CashierId()));

        // Both shifts really are open and holding their own slots — the
        // refusals above must not come from a blanket "one shift at a time".
        $this->assertTrue($this->shiftRepository->load($firstShift)->isAssigned() === false);
        $this->assertTrue($this->shiftRepository->load($secondShift)->isAssigned() === false);
        $this->assertNotSame(
            $this->shiftRepository->load($firstShift)->openedBy()->toNative(),
            $this->shiftRepository->load($secondShift)->openedBy()->toNative()
        );
    }

    public function test_a_closed_shift_id_cannot_be_reopened(): void
    {
        // Replay keeps whatever the first life set that a second ShiftOpened
        // does not overwrite — assignee, cash drops — so the reopened shift
        // would name an operator who is free to run another shift elsewhere.
        $shiftId = new ShiftId();
        ($this->handler)($this->openShiftCommand($shiftId, new TerminalId(), new CashierId()));
        $forceCloseHandler = new ForceCloseShiftHandler($this->shiftRepository, $this->slotReservation);
        $forceCloseHandler(new ForceCloseShift($shiftId->toNative(), 'sup-1', 'end of day'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('a shift id cannot be reused');

        ($this->handler)($this->openShiftCommand($shiftId, new TerminalId(), new CashierId()));
    }

    public function test_an_open_shift_id_cannot_be_reopened(): void
    {
        $shiftId = new ShiftId();
        ($this->handler)($this->openShiftCommand($shiftId, new TerminalId(), new CashierId()));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('a shift id cannot be reused');

        ($this->handler)($this->openShiftCommand($shiftId, new TerminalId(), new CashierId()));
    }

    public function test_a_shift_that_appears_after_the_existence_check_still_wins(): void
    {
        // The existence check is a check, not a claim: a whole shift life can
        // happen in the window between it and the append — and a closed shift
        // has released its slots, so the slot claim cannot catch it either.
        // Storing against version 0 is what refuses the second ShiftOpened.
        $shiftId  = new ShiftId();
        $rivalOpener = new CashierId();

        $handler    = $this->handler;
        $repository = new InterleavingShiftRepository(
            $this->shiftRepository,
            null,
            function () use ($handler, $shiftId, $rivalOpener): void {
                $handler($this->openShiftCommand($shiftId, new TerminalId(), $rivalOpener));
                $forceClose = new ForceCloseShiftHandler($this->shiftRepository, $this->slotReservation);
                $forceClose(new ForceCloseShift($shiftId->toNative(), 'sup-1', 'end of day'));
            }
        );
        $losingHandler = new OpenShiftHandler($repository, $this->slotReservation);

        $this->expectException(ConcurrencyException::class);

        $losingHandler($this->openShiftCommand($shiftId, new TerminalId(), new CashierId()));
    }

    public function test_a_failed_store_releases_the_reserved_slots(): void
    {
        $terminalId = new TerminalId();
        $cashierId  = new CashierId();

        $failingRepository = new class ($this->shiftRepository) implements ShiftRepositoryInterface {
            public bool $failNextStore = true;

            public function __construct(private readonly InMemoryShiftRepository $inner)
            {
            }

            public function load(ShiftId $shiftId): Shift
            {
                return $this->inner->load($shiftId);
            }

            public function store(Shift $shift, ?int $expectedVersion = null): void
            {
                if ($this->failNextStore) {
                    $this->failNextStore = false;
                    throw new \RuntimeException('store unavailable');
                }
                $this->inner->store($shift, $expectedVersion);
            }
        };
        $handler = new OpenShiftHandler($failingRepository, $this->slotReservation);

        try {
            $handler($this->openShiftCommand(new ShiftId(), $terminalId, $cashierId));
            $this->fail('Expected the failing store to throw');
        } catch (\RuntimeException) {
        }

        // The slots were released, so the same terminal and cashier open fine.
        $this->expectNotToPerformAssertions();
        $handler($this->openShiftCommand(new ShiftId(), $terminalId, $cashierId));
    }

    private function openShiftCommand(ShiftId $shiftId, TerminalId $terminalId, CashierId $cashierId): OpenShift
    {
        return new OpenShift(
            $shiftId->toNative(),
            $terminalId->toNative(),
            (new BranchId())->toNative(),
            $cashierId->toNative(),
            50000,
            'PHP'
        );
    }
}
