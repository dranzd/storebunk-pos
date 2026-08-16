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
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDecommissioned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Terminal\Repository\InMemoryTerminalRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class DecommissionTerminalHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryTerminalRepository $terminalRepository;
    private DecommissionTerminalHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->terminalRepository = new InMemoryTerminalRepository($this->eventStore);
        $this->handler = new DecommissionTerminalHandler($this->terminalRepository);
    }

    public function test_decommissions_a_disabled_terminal_with_a_reason(): void
    {
        $terminalId = $this->registerTerminal();
        $disable = new DisableTerminalHandler($this->terminalRepository);
        $disable(new DisableTerminal($terminalId->toNative()));

        ($this->handler)(new DecommissionTerminal($terminalId->toNative(), 'end of life'));

        $decommissioned = array_values(array_filter(
            $this->eventStore->loadEvents($terminalId->toNative()),
            fn ($e) => $e instanceof TerminalDecommissioned
        ));

        $this->assertCount(1, $decommissioned);
        $this->assertSame('end of life', $decommissioned[0]->getReason());
    }

    public function test_refuses_to_decommission_an_active_terminal(): void
    {
        $terminalId = $this->registerTerminal();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot decommission an active terminal');

        ($this->handler)(new DecommissionTerminal($terminalId->toNative(), 'end of life'));
    }

    public function test_refuses_to_decommission_twice(): void
    {
        $terminalId = $this->registerTerminal();
        $disable = new DisableTerminalHandler($this->terminalRepository);
        $disable(new DisableTerminal($terminalId->toNative()));
        ($this->handler)(new DecommissionTerminal($terminalId->toNative(), 'end of life'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Terminal is already decommissioned');

        ($this->handler)(new DecommissionTerminal($terminalId->toNative(), 'again'));
    }

    private function registerTerminal(): TerminalId
    {
        $terminalId = new TerminalId();
        $register = new RegisterTerminalHandler($this->terminalRepository);
        $register(new RegisterTerminal($terminalId->toNative(), (new BranchId())->toNative(), 'POS-01'));

        return $terminalId;
    }
}
