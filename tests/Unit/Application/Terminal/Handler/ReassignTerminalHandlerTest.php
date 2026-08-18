<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Terminal\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Terminal\Command\DisableTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DisableTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\ReassignTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RegisterTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\ReassignTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalReassigned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Terminal\Repository\InMemoryTerminalRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class ReassignTerminalHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryTerminalRepository $terminalRepository;
    private ReassignTerminalHandler $handler;
    private BranchId $originalBranchId;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->terminalRepository = new InMemoryTerminalRepository($this->eventStore);
        $this->handler = new ReassignTerminalHandler($this->terminalRepository);
        $this->originalBranchId = new BranchId();
    }

    public function test_reassigns_a_disabled_terminal_to_a_new_branch(): void
    {
        $terminalId = $this->registerTerminal();
        $disable = new DisableTerminalHandler($this->terminalRepository);
        $disable(new DisableTerminal($terminalId->toNative()));
        $newBranchId = new BranchId();

        ($this->handler)(new ReassignTerminal($terminalId->toNative(), $newBranchId->toNative()));

        $reassigned = array_values(array_filter(
            $this->eventStore->loadEvents($terminalId->toNative()),
            fn ($e) => $e instanceof TerminalReassigned
        ));

        $this->assertCount(1, $reassigned);
        $this->assertSame($this->originalBranchId->toNative(), $reassigned[0]->getOldBranchId()->toNative());
        $this->assertSame($newBranchId->toNative(), $reassigned[0]->getNewBranchId()->toNative());
    }

    public function test_refuses_to_reassign_an_active_terminal(): void
    {
        $terminalId = $this->registerTerminal();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot reassign an active terminal');

        ($this->handler)(new ReassignTerminal($terminalId->toNative(), (new BranchId())->toNative()));
    }

    public function test_refuses_to_reassign_to_the_same_branch(): void
    {
        $terminalId = $this->registerTerminal();
        $disable = new DisableTerminalHandler($this->terminalRepository);
        $disable(new DisableTerminal($terminalId->toNative()));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('New branch is the same as the current branch');

        ($this->handler)(new ReassignTerminal($terminalId->toNative(), $this->originalBranchId->toNative()));
    }

    private function registerTerminal(): TerminalId
    {
        $terminalId = new TerminalId();
        $register = new RegisterTerminalHandler($this->terminalRepository);
        $register(new RegisterTerminal($terminalId->toNative(), $this->originalBranchId->toNative(), 'POS-01'));

        return $terminalId;
    }
}
