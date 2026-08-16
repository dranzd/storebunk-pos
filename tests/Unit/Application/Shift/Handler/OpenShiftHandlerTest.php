<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shift\Handler;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\OpenShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftForceClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftOpened;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\MultiTerminalEnforcementService;
use Dranzd\StorebunkPos\Infrastructure\Shift\ReadModel\InMemoryShiftReadModel;
use Dranzd\StorebunkPos\Infrastructure\Shift\Repository\InMemoryShiftRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class OpenShiftHandlerTest extends TestCase
{
    private InMemoryShiftRepository $shiftRepository;
    private InMemoryShiftReadModel $readModel;
    private OpenShiftHandler $handler;

    protected function setUp(): void
    {
        $this->shiftRepository = new InMemoryShiftRepository(new InMemoryEventStore());
        $this->readModel = new InMemoryShiftReadModel();
        $this->handler = new OpenShiftHandler(
            $this->shiftRepository,
            new MultiTerminalEnforcementService(),
            $this->readModel
        );
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
        $this->openAndProject(new ShiftId(), $terminalId, new CashierId());

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');

        ($this->handler)($this->openShiftCommand(new ShiftId(), $terminalId, new CashierId()));
    }

    public function test_refuses_a_second_shift_for_the_same_cashier_on_another_terminal(): void
    {
        $cashierId = new CashierId();
        $this->openAndProject(new ShiftId(), new TerminalId(), $cashierId);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift on another terminal');

        ($this->handler)($this->openShiftCommand(new ShiftId(), new TerminalId(), $cashierId));
    }

    public function test_allows_reopening_after_the_previous_shift_closed(): void
    {
        $terminalId = new TerminalId();
        $cashierId  = new CashierId();
        $firstShiftId = new ShiftId();
        $this->openAndProject($firstShiftId, $terminalId, $cashierId);

        $money = Money::fromArray(['amount' => 50000, 'currency' => 'PHP']);
        $this->readModel->onShiftClosed(ShiftClosed::occur(
            $firstShiftId,
            $money,
            $money,
            Money::fromArray(['amount' => 0, 'currency' => 'PHP']),
            new \DateTimeImmutable()
        ));

        $this->expectNotToPerformAssertions();

        ($this->handler)($this->openShiftCommand(new ShiftId(), $terminalId, $cashierId));
    }

    public function test_allows_reopening_after_the_previous_shift_was_force_closed(): void
    {
        $terminalId = new TerminalId();
        $firstShiftId = new ShiftId();
        $this->openAndProject($firstShiftId, $terminalId, new CashierId());

        $this->readModel->onShiftForceClosed(ShiftForceClosed::occur(
            $firstShiftId,
            'supervisor-1',
            'drawer jammed',
            new \DateTimeImmutable()
        ));

        $this->expectNotToPerformAssertions();

        ($this->handler)($this->openShiftCommand(new ShiftId(), $terminalId, new CashierId()));
    }

    public function test_different_terminals_and_cashiers_open_concurrently(): void
    {
        $this->openAndProject(new ShiftId(), new TerminalId(), new CashierId());

        $this->expectNotToPerformAssertions();

        ($this->handler)($this->openShiftCommand(new ShiftId(), new TerminalId(), new CashierId()));
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

    private function openAndProject(ShiftId $shiftId, TerminalId $terminalId, CashierId $cashierId): void
    {
        ($this->handler)($this->openShiftCommand($shiftId, $terminalId, $cashierId));

        // The handler reads, never writes, the read model — mirror the host's
        // projection step so the enforcement sees the shift we just opened.
        $this->readModel->onShiftOpened(ShiftOpened::occur(
            $shiftId,
            $terminalId,
            new BranchId(),
            $cashierId,
            Money::fromArray(['amount' => 50000, 'currency' => 'PHP']),
            new \DateTimeImmutable()
        ));
    }
}
