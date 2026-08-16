<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Terminal\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Terminal\Command\DecommissionTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\DisableTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DecommissionTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DisableTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RegisterTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDisabled;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Terminal\Repository\InMemoryTerminalRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class DisableTerminalHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryTerminalRepository $terminalRepository;
    private DisableTerminalHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->terminalRepository = new InMemoryTerminalRepository($this->eventStore);
        $this->handler = new DisableTerminalHandler($this->terminalRepository);
    }

    public function test_disables_an_active_terminal(): void
    {
        $terminalId = $this->registerTerminal();

        ($this->handler)(new DisableTerminal($terminalId->toNative()));

        $disabled = array_values(array_filter(
            $this->eventStore->loadEvents($terminalId->toNative()),
            fn ($e) => $e instanceof TerminalDisabled
        ));

        $this->assertCount(1, $disabled);
    }

    public function test_refuses_to_disable_a_decommissioned_terminal(): void
    {
        $terminalId = $this->registerTerminal();
        ($this->handler)(new DisableTerminal($terminalId->toNative()));
        $decommission = new DecommissionTerminalHandler($this->terminalRepository);
        $decommission(new DecommissionTerminal($terminalId->toNative(), 'end of life'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot disable a decommissioned terminal');

        ($this->handler)(new DisableTerminal($terminalId->toNative()));
    }

    private function registerTerminal(): TerminalId
    {
        $terminalId = new TerminalId();
        $register = new RegisterTerminalHandler($this->terminalRepository);
        $register(new RegisterTerminal($terminalId->toNative(), (new BranchId())->toNative(), 'POS-01'));

        return $terminalId;
    }
}
