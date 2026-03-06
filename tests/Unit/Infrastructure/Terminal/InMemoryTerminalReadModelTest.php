<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Infrastructure\Terminal;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalActivated;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDecommissioned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDisabled;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalMaintenanceSet;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalReassigned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRecommissioned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRenamed;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Terminal\ReadModel\InMemoryTerminalReadModel;
use PHPUnit\Framework\TestCase;

final class InMemoryTerminalReadModelTest extends TestCase
{
    private InMemoryTerminalReadModel $readModel;

    protected function setUp(): void
    {
        $this->readModel = new InMemoryTerminalReadModel();
    }

    public function test_it_projects_terminal_registered_event(): void
    {
        $terminalId = new TerminalId();
        $branchId = new BranchId();
        $event = TerminalRegistered::occur(
            $terminalId,
            $branchId,
            'Terminal 1',
            new DateTimeImmutable()
        );

        $this->readModel->onTerminalRegistered($event);

        $terminal = $this->readModel->getTerminal($terminalId->toNative());
        $this->assertNotNull($terminal);
        $this->assertSame($terminalId->toNative(), $terminal['terminal_id']);
        $this->assertSame($branchId->toNative(), $terminal['branch_id']);
        $this->assertSame('Terminal 1', $terminal['name']);
        $this->assertSame('active', $terminal['status']);
    }

    public function test_it_projects_terminal_disabled_event(): void
    {
        $terminalId = new TerminalId();
        $this->registerTerminal($terminalId);

        $event = TerminalDisabled::occur($terminalId, new DateTimeImmutable());
        $this->readModel->onTerminalDisabled($event);

        $terminal = $this->readModel->getTerminal($terminalId->toNative());
        $this->assertSame('disabled', $terminal['status']);
    }

    public function test_it_projects_terminal_activated_event(): void
    {
        $terminalId = new TerminalId();
        $this->registerTerminal($terminalId);

        $event = TerminalActivated::occur($terminalId, new DateTimeImmutable());
        $this->readModel->onTerminalActivated($event);

        $terminal = $this->readModel->getTerminal($terminalId->toNative());
        $this->assertSame('active', $terminal['status']);
    }

    public function test_it_projects_terminal_maintenance_set_event(): void
    {
        $terminalId = new TerminalId();
        $this->registerTerminal($terminalId);

        $event = TerminalMaintenanceSet::occur($terminalId, new DateTimeImmutable());
        $this->readModel->onTerminalMaintenanceSet($event);

        $terminal = $this->readModel->getTerminal($terminalId->toNative());
        $this->assertSame('maintenance', $terminal['status']);
    }

    public function test_it_can_get_all_terminals(): void
    {
        $this->registerTerminal(new TerminalId());
        $this->registerTerminal(new TerminalId());

        $terminals = $this->readModel->getAllTerminals();
        $this->assertCount(2, $terminals);
    }

    public function test_it_can_filter_terminals_by_branch(): void
    {
        $branchId1 = new BranchId();
        $branchId2 = new BranchId();

        $this->registerTerminalWithBranch(new TerminalId(), $branchId1);
        $this->registerTerminalWithBranch(new TerminalId(), $branchId1);
        $this->registerTerminalWithBranch(new TerminalId(), $branchId2);

        $terminals = $this->readModel->getTerminalsByBranch($branchId1->toNative());
        $this->assertCount(2, $terminals);
    }

    public function test_it_can_filter_terminals_by_status(): void
    {
        $terminal1 = new TerminalId();
        $terminal2 = new TerminalId();
        $terminal3 = new TerminalId();

        $this->registerTerminal($terminal1);
        $this->registerTerminal($terminal2);
        $this->registerTerminal($terminal3);

        $this->readModel->onTerminalDisabled(
            TerminalDisabled::occur($terminal2, new DateTimeImmutable())
        );

        $activeTerminals = $this->readModel->getTerminalsByStatus('active');
        $this->assertCount(2, $activeTerminals);

        $disabledTerminals = $this->readModel->getTerminalsByStatus('disabled');
        $this->assertCount(1, $disabledTerminals);
    }

    public function test_it_projects_terminal_renamed_event(): void
    {
        $terminalId = new TerminalId();
        $this->registerTerminal($terminalId);

        $event = TerminalRenamed::occur($terminalId, 'Terminal', 'POS Station 2', new DateTimeImmutable());
        $this->readModel->onTerminalRenamed($event);

        $terminal = $this->readModel->getTerminal($terminalId->toNative());
        $this->assertSame('POS Station 2', $terminal['name']);
    }

    public function test_it_projects_terminal_reassigned_event(): void
    {
        $terminalId = new TerminalId();
        $oldBranch  = new BranchId();
        $newBranch  = new BranchId();
        $this->registerTerminalWithBranch($terminalId, $oldBranch);

        $event = TerminalReassigned::occur($terminalId, $oldBranch, $newBranch, new DateTimeImmutable());
        $this->readModel->onTerminalReassigned($event);

        $terminal = $this->readModel->getTerminal($terminalId->toNative());
        $this->assertSame($newBranch->toNative(), $terminal['branch_id']);
    }

    public function test_it_projects_terminal_decommissioned_event(): void
    {
        $terminalId = new TerminalId();
        $this->registerTerminal($terminalId);

        $event = TerminalDecommissioned::occur($terminalId, 'end of life', new DateTimeImmutable());
        $this->readModel->onTerminalDecommissioned($event);

        $terminal = $this->readModel->getTerminal($terminalId->toNative());
        $this->assertSame('decommissioned', $terminal['status']);
    }

    public function test_it_projects_terminal_recommissioned_event(): void
    {
        $terminalId = new TerminalId();
        $this->registerTerminal($terminalId);
        $this->readModel->onTerminalDecommissioned(
            TerminalDecommissioned::occur($terminalId, 'end of life', new DateTimeImmutable())
        );

        $event = TerminalRecommissioned::occur($terminalId, 'returned to service', new DateTimeImmutable());
        $this->readModel->onTerminalRecommissioned($event);

        $terminal = $this->readModel->getTerminal($terminalId->toNative());
        $this->assertSame('disabled', $terminal['status']);
    }

    private function registerTerminal(TerminalId $terminalId): void
    {
        $this->registerTerminalWithBranch($terminalId, new BranchId());
    }

    private function registerTerminalWithBranch(TerminalId $terminalId, BranchId $branchId): void
    {
        $event = TerminalRegistered::occur(
            $terminalId,
            $branchId,
            'Terminal',
            new DateTimeImmutable()
        );
        $this->readModel->onTerminalRegistered($event);
    }
}
