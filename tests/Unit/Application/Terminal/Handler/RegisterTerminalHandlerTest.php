<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Terminal\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RegisterTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Terminal\Repository\InMemoryTerminalRepository;
use PHPUnit\Framework\TestCase;

final class RegisterTerminalHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryTerminalRepository $terminalRepository;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->terminalRepository = new InMemoryTerminalRepository($this->eventStore);
    }

    public function test_registers_a_terminal_and_persists_the_event(): void
    {
        $terminalId = new TerminalId();
        $branchId = new BranchId();

        $handler = new RegisterTerminalHandler($this->terminalRepository);
        $handler(new RegisterTerminal($terminalId->toNative(), $branchId->toNative(), 'POS-01'));

        $events = $this->eventStore->loadEvents($terminalId->toNative());

        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalRegistered::class, $events[0]);
        $this->assertSame('POS-01', $events[0]->getName());
        $this->assertSame($branchId->toNative(), $events[0]->getBranchId()->toNative());
    }

    public function test_registered_terminal_reloads_from_the_repository(): void
    {
        $terminalId = new TerminalId();

        $handler = new RegisterTerminalHandler($this->terminalRepository);
        $handler(new RegisterTerminal($terminalId->toNative(), (new BranchId())->toNative(), 'POS-01'));

        $terminal = $this->terminalRepository->load($terminalId);

        $this->assertSame($terminalId->toNative(), $terminal->getAggregateRootUuid());
    }
}
