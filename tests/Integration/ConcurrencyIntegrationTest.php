<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Integration;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Terminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalStatus;
use Dranzd\StorebunkPos\Infrastructure\Terminal\Repository\InMemoryTerminalRepository;
use Dranzd\StorebunkPos\Shared\Exception\ConcurrencyException;
use PHPUnit\Framework\TestCase;

final class ConcurrencyIntegrationTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryTerminalRepository $repository;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->repository = new InMemoryTerminalRepository($this->eventStore);
    }

    public function test_concurrent_modification_throws_exception(): void
    {
        $terminalId = new TerminalId();
        $branchId = new BranchId();

        $terminal = Terminal::register($terminalId, $branchId, 'Terminal 1');
        $this->repository->store($terminal);

        $terminal1 = $this->repository->load($terminalId);
        $version1 = $terminal1->getAggregateRootVersion();

        $terminal2 = $this->repository->load($terminalId);
        $version2 = $terminal2->getAggregateRootVersion();

        $this->assertSame($version1, $version2);

        $terminal1->activate();
        $this->repository->store($terminal1);

        $terminal2->disable();

        $this->expectException(ConcurrencyException::class);
        $this->expectExceptionMessage('Concurrency conflict');

        $this->repository->store($terminal2, $version2);
    }

    public function test_sequential_modifications_succeed(): void
    {
        $terminalId = new TerminalId();
        $branchId = new BranchId();

        $terminal = Terminal::register($terminalId, $branchId, 'Terminal 1');
        $this->repository->store($terminal);

        $terminal = $this->repository->load($terminalId);
        $version = $terminal->getAggregateRootVersion();
        $terminal->activate();
        $this->repository->store($terminal, $version);

        $terminal = $this->repository->load($terminalId);
        $version = $terminal->getAggregateRootVersion();
        $terminal->disable();
        $this->repository->store($terminal, $version);

        $terminal = $this->repository->load($terminalId);
        $this->assertSame(3, $terminal->getAggregateRootVersion());
    }

    public function test_store_without_version_check_always_succeeds(): void
    {
        $terminalId = new TerminalId();
        $branchId = new BranchId();

        $terminal = Terminal::register($terminalId, $branchId, 'Terminal 1');
        $this->repository->store($terminal);

        $terminal1 = $this->repository->load($terminalId);
        $terminal2 = $this->repository->load($terminalId);

        $terminal1->activate();
        $this->repository->store($terminal1);

        $terminal2->disable();
        $this->repository->store($terminal2);

        $terminal = $this->repository->load($terminalId);
        $this->assertSame(2, $terminal->getAggregateRootVersion());
    }

    public function test_optimistic_locking_detects_version_mismatch(): void
    {
        $terminalId = new TerminalId();
        $branchId = new BranchId();

        $terminal = Terminal::register($terminalId, $branchId, 'Terminal 1');
        $this->repository->store($terminal);

        $terminal1 = $this->repository->load($terminalId);
        $terminal2 = $this->repository->load($terminalId);

        $terminal1->activate();
        $this->repository->store($terminal1, 1);

        $this->expectException(ConcurrencyException::class);

        $terminal2->setMaintenance();
        $this->repository->store($terminal2, 1);
    }
}
