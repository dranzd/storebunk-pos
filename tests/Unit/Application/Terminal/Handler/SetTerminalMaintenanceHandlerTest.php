<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Terminal\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Terminal\Command\DecommissionTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\DisableTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DecommissionTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DisableTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RegisterTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\SetTerminalMaintenanceHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\SetTerminalMaintenance;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalMaintenanceSet;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Terminal\Repository\InMemoryTerminalRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class SetTerminalMaintenanceHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryTerminalRepository $terminalRepository;
    private SetTerminalMaintenanceHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->terminalRepository = new InMemoryTerminalRepository($this->eventStore);
        $this->handler = new SetTerminalMaintenanceHandler($this->terminalRepository);
    }

    public function test_sets_an_active_terminal_to_maintenance(): void
    {
        $terminalId = $this->registerTerminal();

        ($this->handler)(new SetTerminalMaintenance($terminalId->toNative()));

        $maintenance = array_values(array_filter(
            $this->eventStore->loadEvents($terminalId->toNative()),
            fn ($e) => $e instanceof TerminalMaintenanceSet
        ));

        $this->assertCount(1, $maintenance);
    }

    public function test_refuses_maintenance_on_a_decommissioned_terminal(): void
    {
        $terminalId = $this->registerTerminal();
        $disable = new DisableTerminalHandler($this->terminalRepository);
        $disable(new DisableTerminal($terminalId->toNative()));
        $decommission = new DecommissionTerminalHandler($this->terminalRepository);
        $decommission(new DecommissionTerminal($terminalId->toNative(), 'end of life'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot set a decommissioned terminal to maintenance');

        ($this->handler)(new SetTerminalMaintenance($terminalId->toNative()));
    }

    private function registerTerminal(): TerminalId
    {
        $terminalId = new TerminalId();
        $register = new RegisterTerminalHandler($this->terminalRepository);
        $register(new RegisterTerminal($terminalId->toNative(), (new BranchId())->toNative(), 'POS-01'));

        return $terminalId;
    }
}
