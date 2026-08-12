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
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class AssignShiftHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryShiftRepository $shiftRepository;
    private AssignShiftHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore      = new InMemoryEventStore();
        $this->shiftRepository = new InMemoryShiftRepository($this->eventStore);
        $this->handler         = new AssignShiftHandler($this->shiftRepository);
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

    private function openShift(): ShiftId
    {
        $shiftId = new ShiftId();
        $openHandler = new OpenShiftHandler($this->shiftRepository);
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
