<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Model\Terminal;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalActivated;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDecommissioned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDisabled;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalMaintenanceSet;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalReassigned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRecommissioned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRenamed;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Terminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class TerminalTest extends TestCase
{
    public function test_it_can_be_registered(): void
    {
        $terminalId = new TerminalId();
        $branchId = new BranchId();
        $name = 'Terminal 1';

        $terminal = Terminal::register($terminalId, $branchId, $name);

        $events = $terminal->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalRegistered::class, $events[0]);

        $event = $events[0];
        assert($event instanceof TerminalRegistered);
        $this->assertTrue($event->terminalId()->sameValueAs($terminalId));
        $this->assertTrue($event->branchId()->sameValueAs($branchId));
        $this->assertSame($name, $event->name());
        $this->assertInstanceOf(DateTimeImmutable::class, $event->registeredAt());
    }

    public function test_it_can_be_activated(): void
    {
        $terminal = $this->createRegisteredTerminal();
        $terminal->popRecordedEvents();

        $terminal->activate();

        $events = $terminal->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalActivated::class, $events[0]);

        $event = $events[0];
        assert($event instanceof TerminalActivated);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->activatedAt());
    }

    public function test_it_can_be_disabled(): void
    {
        $terminal = $this->createRegisteredTerminal();
        $terminal->popRecordedEvents();

        $terminal->disable();

        $events = $terminal->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalDisabled::class, $events[0]);

        $event = $events[0];
        assert($event instanceof TerminalDisabled);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->disabledAt());
    }

    public function test_it_can_be_set_to_maintenance(): void
    {
        $terminal = $this->createRegisteredTerminal();
        $terminal->popRecordedEvents();

        $terminal->setMaintenance();

        $events = $terminal->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalMaintenanceSet::class, $events[0]);

        $event = $events[0];
        assert($event instanceof TerminalMaintenanceSet);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->maintenanceSetAt());
    }

    public function test_it_can_be_reconstituted_from_history(): void
    {
        $terminalId = new TerminalId();
        $branchId = new BranchId();

        // Create a terminal and get its events with proper metadata
        $originalTerminal = Terminal::register($terminalId, $branchId, 'Terminal 1');
        $originalTerminal->disable();
        $originalTerminal->activate();
        $events = $originalTerminal->popRecordedEvents();

        // Reconstitute from those events
        $terminal = new Terminal();
        $terminal = $terminal->reconstituteFromHistory($events);

        $this->assertInstanceOf(Terminal::class, $terminal);
        $this->assertSame($terminalId->toNative(), $terminal->getAggregateRootUuid());
        $this->assertSame(3, $terminal->getAggregateRootVersion());
        $this->assertEmpty($terminal->popRecordedEvents());
    }

    public function test_it_can_be_renamed(): void
    {
        $terminal = $this->createRegisteredTerminal();
        $terminal->popRecordedEvents();

        $terminal->rename('Counter A');

        $events = $terminal->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalRenamed::class, $events[0]);

        $event = $events[0];
        assert($event instanceof TerminalRenamed);
        $this->assertSame('Test Terminal', $event->oldName());
        $this->assertSame('Counter A', $event->newName());
        $this->assertInstanceOf(DateTimeImmutable::class, $event->renamedAt());
    }

    public function test_rename_rejects_same_name(): void
    {
        $terminal = $this->createRegisteredTerminal();

        $this->expectException(InvariantViolationException::class);
        $terminal->rename('Test Terminal');
    }

    public function test_rename_rejects_decommissioned_terminal(): void
    {
        $terminal = $this->createDisabledTerminal();
        $terminal->decommission('End of life');
        $terminal->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $terminal->rename('New Name');
    }

    public function test_it_can_be_reassigned_to_another_branch(): void
    {
        $originalBranchId = new BranchId();
        $newBranchId = new BranchId();
        $terminal = $this->createDisabledTerminalWithBranch($originalBranchId);
        $terminal->popRecordedEvents();

        $terminal->reassign($newBranchId);

        $events = $terminal->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalReassigned::class, $events[0]);

        $event = $events[0];
        assert($event instanceof TerminalReassigned);
        $this->assertTrue($event->oldBranchId()->sameValueAs($originalBranchId));
        $this->assertTrue($event->newBranchId()->sameValueAs($newBranchId));
        $this->assertInstanceOf(DateTimeImmutable::class, $event->reassignedAt());
    }

    public function test_reassign_rejects_active_terminal(): void
    {
        $terminal = $this->createRegisteredTerminal();

        $this->expectException(InvariantViolationException::class);
        $terminal->reassign(new BranchId());
    }

    public function test_reassign_rejects_same_branch(): void
    {
        $branchId = new BranchId();
        $terminal = $this->createDisabledTerminalWithBranch($branchId);

        $this->expectException(InvariantViolationException::class);
        $terminal->reassign($branchId);
    }

    public function test_reassign_rejects_decommissioned_terminal(): void
    {
        $terminal = $this->createDisabledTerminal();
        $terminal->decommission('End of life');
        $terminal->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $terminal->reassign(new BranchId());
    }

    public function test_it_can_be_decommissioned_from_disabled(): void
    {
        $terminal = $this->createDisabledTerminal();
        $terminal->popRecordedEvents();

        $terminal->decommission('Hardware failure');

        $events = $terminal->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalDecommissioned::class, $events[0]);

        $event = $events[0];
        assert($event instanceof TerminalDecommissioned);
        $this->assertSame('Hardware failure', $event->reason());
        $this->assertInstanceOf(DateTimeImmutable::class, $event->decommissionedAt());
    }

    public function test_it_can_be_decommissioned_from_maintenance(): void
    {
        $terminal = $this->createRegisteredTerminal();
        $terminal->setMaintenance();
        $terminal->popRecordedEvents();

        $terminal->decommission('Replaced by new unit');

        $events = $terminal->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalDecommissioned::class, $events[0]);
    }

    public function test_decommission_rejects_active_terminal(): void
    {
        $terminal = $this->createRegisteredTerminal();

        $this->expectException(InvariantViolationException::class);
        $terminal->decommission('Should fail');
    }

    public function test_decommission_rejects_already_decommissioned_terminal(): void
    {
        $terminal = $this->createDisabledTerminal();
        $terminal->decommission('First decommission');
        $terminal->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $terminal->decommission('Second decommission');
    }

    public function test_it_can_be_recommissioned(): void
    {
        $terminal = $this->createDisabledTerminal();
        $terminal->decommission('Temporary retirement');
        $terminal->popRecordedEvents();

        $terminal->recommission('Returned to service');

        $events = $terminal->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalRecommissioned::class, $events[0]);

        $event = $events[0];
        assert($event instanceof TerminalRecommissioned);
        $this->assertSame('Returned to service', $event->reason());
        $this->assertInstanceOf(DateTimeImmutable::class, $event->recommissionedAt());
    }

    public function test_recommission_rejects_non_decommissioned_terminal(): void
    {
        $terminal = $this->createDisabledTerminal();

        $this->expectException(InvariantViolationException::class);
        $terminal->recommission('Should fail');
    }

    public function test_recommissioned_terminal_can_be_activated(): void
    {
        $terminal = $this->createDisabledTerminal();
        $terminal->decommission('Temporary retirement');
        $terminal->recommission('Returned to service');
        $terminal->popRecordedEvents();

        $terminal->activate();

        $events = $terminal->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalActivated::class, $events[0]);
    }

    public function test_decommissioned_terminal_blocks_activate(): void
    {
        $terminal = $this->createDisabledTerminal();
        $terminal->decommission('End of life');
        $terminal->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $terminal->activate();
    }

    public function test_decommissioned_terminal_blocks_disable(): void
    {
        $terminal = $this->createDisabledTerminal();
        $terminal->decommission('End of life');
        $terminal->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $terminal->disable();
    }

    public function test_decommissioned_terminal_blocks_set_maintenance(): void
    {
        $terminal = $this->createDisabledTerminal();
        $terminal->decommission('End of life');
        $terminal->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $terminal->setMaintenance();
    }

    public function test_it_can_be_reconstituted_with_full_lifecycle(): void
    {
        $terminalId = new TerminalId();
        $branchId = new BranchId();
        $newBranchId = new BranchId();

        $original = Terminal::register($terminalId, $branchId, 'Till 1');
        $original->rename('Express Lane');
        $original->disable();
        $original->reassign($newBranchId);
        $original->decommission('End of life');
        $original->recommission('Returned to service');
        $original->activate();
        $events = $original->popRecordedEvents();

        $terminal = new Terminal();
        $terminal = $terminal->reconstituteFromHistory($events);

        $this->assertInstanceOf(Terminal::class, $terminal);
        $this->assertSame($terminalId->toNative(), $terminal->getAggregateRootUuid());
        $this->assertSame(7, $terminal->getAggregateRootVersion());
        $this->assertEmpty($terminal->popRecordedEvents());
    }

    private function createRegisteredTerminal(): Terminal
    {
        return Terminal::register(
            new TerminalId(),
            new BranchId(),
            'Test Terminal'
        );
    }

    private function createDisabledTerminal(): Terminal
    {
        $terminal = Terminal::register(new TerminalId(), new BranchId(), 'Test Terminal');
        $terminal->disable();

        return $terminal;
    }

    private function createDisabledTerminalWithBranch(BranchId $branchId): Terminal
    {
        $terminal = Terminal::register(new TerminalId(), $branchId, 'Test Terminal');
        $terminal->disable();

        return $terminal;
    }
}
