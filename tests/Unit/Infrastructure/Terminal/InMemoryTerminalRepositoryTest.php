<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Infrastructure\Terminal;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Terminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Terminal\Repository\InMemoryTerminalRepository;
use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;
use PHPUnit\Framework\TestCase;

final class InMemoryTerminalRepositoryTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryTerminalRepository $repository;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->repository = new InMemoryTerminalRepository($this->eventStore);
    }

    public function test_it_can_store_and_load_terminal(): void
    {
        $terminalId = new TerminalId();
        $terminal = Terminal::register(
            $terminalId,
            new BranchId(),
            'Terminal 1'
        );

        $this->repository->store($terminal);
        $loadedTerminal = $this->repository->load($terminalId);

        $this->assertInstanceOf(Terminal::class, $loadedTerminal);
        $this->assertSame($terminalId->toNative(), $loadedTerminal->getAggregateRootUuid());
    }

    public function test_it_throws_exception_when_terminal_not_found(): void
    {
        $terminalId = new TerminalId();

        $this->expectException(AggregateNotFoundException::class);
        $this->expectExceptionMessage('Terminal');

        $this->repository->load($terminalId);
    }

    public function test_it_persists_terminal_state_changes(): void
    {
        $terminalId = new TerminalId();
        $terminal = Terminal::register(
            $terminalId,
            new BranchId(),
            'Terminal 1'
        );

        $this->repository->store($terminal);

        $loadedTerminal = $this->repository->load($terminalId);
        $loadedTerminal->disable();
        $this->repository->store($loadedTerminal);

        $reloadedTerminal = $this->repository->load($terminalId);
        $this->assertSame(2, $reloadedTerminal->getAggregateRootVersion());
    }
}
