<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Model\Terminal;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalActivated;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDisabled;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalMaintenanceSet;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Terminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
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

    private function createRegisteredTerminal(): Terminal
    {
        return Terminal::register(
            new TerminalId(),
            new BranchId(),
            'Test Terminal'
        );
    }
}
