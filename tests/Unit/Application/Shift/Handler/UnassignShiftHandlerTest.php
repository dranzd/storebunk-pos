<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shift\Handler;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\AssignShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\OpenShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\UnassignShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Application\Shift\Command\UnassignShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftAssigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftOpened;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftUnassigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\MultiTerminalEnforcementService;
use Dranzd\StorebunkPos\Infrastructure\Shift\ReadModel\InMemoryShiftReadModel;
use Dranzd\StorebunkPos\Infrastructure\Shift\Repository\InMemoryShiftRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class UnassignShiftHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryShiftRepository $shiftRepository;
    private InMemoryShiftReadModel $readModel;
    private UnassignShiftHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore      = new InMemoryEventStore();
        $this->shiftRepository = new InMemoryShiftRepository($this->eventStore);
        $this->readModel       = new InMemoryShiftReadModel();
        $this->handler         = new UnassignShiftHandler(
            $this->shiftRepository,
            new MultiTerminalEnforcementService(),
            $this->readModel
        );
    }

    public function test_clears_membership_back_to_open(): void
    {
        $shiftId = $this->openAssignedShift();

        ($this->handler)(new UnassignShift($shiftId->toNative()));

        $unassigned = array_values(array_filter(
            $this->eventStore->loadEvents($shiftId->toNative()),
            fn ($e) => $e instanceof ShiftUnassigned
        ));
        $this->assertCount(1, $unassigned);

        $shift = $this->shiftRepository->load($shiftId);
        $this->assertFalse($shift->isAssigned());
        $this->assertNull($shift->assignee());
        $this->assertSame([], $shift->fallbackCashiers());
    }

    public function test_rejects_unassign_when_not_assigned(): void
    {
        $shiftId = $this->openShift();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Shift is not assigned');

        ($this->handler)(new UnassignShift($shiftId->toNative()));
    }

    public function test_refuses_unassign_when_the_opener_operates_another_open_shift(): void
    {
        // Opener opens shift A, hands it to an assignee, then opens shift B.
        // Unassigning A would give the opener two open shifts — refused.
        $opener  = new CashierId();
        $shiftA  = $this->openAssignedShift($opener);
        $this->openShift($opener);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already operates another open shift');

        ($this->handler)(new UnassignShift($shiftA->toNative()));
    }

    private function openShift(?CashierId $cashierId = null): ShiftId
    {
        $shiftId    = new ShiftId();
        $cashierId ??= new CashierId();
        $terminalId = new TerminalId();
        $openHandler = new OpenShiftHandler(
            $this->shiftRepository,
            new MultiTerminalEnforcementService(),
            new InMemoryShiftReadModel()
        );
        $openHandler(new OpenShift(
            $shiftId->toNative(),
            $terminalId->toNative(),
            (new BranchId())->toNative(),
            $cashierId->toNative(),
            50000,
            'PHP'
        ));

        // Mirror the host's projection step so the unassign guard sees it.
        $this->readModel->onShiftOpened(ShiftOpened::occur(
            $shiftId,
            $terminalId,
            new BranchId(),
            $cashierId,
            Money::fromArray(['amount' => 50000, 'currency' => 'PHP']),
            new \DateTimeImmutable()
        ));

        return $shiftId;
    }

    private function openAssignedShift(?CashierId $opener = null): ShiftId
    {
        $shiftId  = $this->openShift($opener);
        $assignee = new CashierId();
        $assignHandler = new AssignShiftHandler(
            $this->shiftRepository,
            new MultiTerminalEnforcementService(),
            $this->readModel
        );
        $assignHandler(new AssignShift(
            $shiftId->toNative(),
            $assignee->toNative(),
            [(new CashierId())->toNative()]
        ));
        $this->readModel->onShiftAssigned(ShiftAssigned::occur(
            $shiftId,
            $assignee,
            [],
            new \DateTimeImmutable()
        ));

        return $shiftId;
    }
}
