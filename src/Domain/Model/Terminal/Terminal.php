<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRoot;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRootTrait;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalActivated;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDisabled;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalMaintenanceSet;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalStatus;

final class Terminal implements AggregateRoot
{
    use AggregateRootTrait;

    private TerminalId $terminalId;
    private BranchId $branchId;
    private string $name;
    private TerminalStatus $status;
    private DateTimeImmutable $registeredAt;

    final public static function register(
        TerminalId $terminalId,
        BranchId $branchId,
        string $name
    ): self {
        $terminal = new self();
        $terminal->terminalId = $terminalId;
        $terminal->recordThat(
            TerminalRegistered::occur($terminalId, $branchId, $name, new DateTimeImmutable())
        );

        return $terminal;
    }

    final public function activate(): void
    {
        $this->recordThat(
            TerminalActivated::occur($this->terminalId, new DateTimeImmutable())
        );
    }

    final public function disable(): void
    {
        $this->recordThat(
            TerminalDisabled::occur($this->terminalId, new DateTimeImmutable())
        );
    }

    final public function setMaintenance(): void
    {
        $this->recordThat(
            TerminalMaintenanceSet::occur($this->terminalId, new DateTimeImmutable())
        );
    }

    final public function getAggregateRootUuid(): string
    {
        return $this->terminalId->toNative();
    }

    private function applyOnTerminalRegistered(TerminalRegistered $event): void
    {
        $this->terminalId = $event->terminalId();
        $this->branchId = $event->branchId();
        $this->name = $event->name();
        $this->status = TerminalStatus::Active;
        $this->registeredAt = $event->registeredAt();
    }

    private function applyOnTerminalActivated(TerminalActivated $event): void
    {
        $this->status = TerminalStatus::Active;
    }

    private function applyOnTerminalDisabled(TerminalDisabled $event): void
    {
        $this->status = TerminalStatus::Disabled;
    }

    private function applyOnTerminalMaintenanceSet(TerminalMaintenanceSet $event): void
    {
        $this->status = TerminalStatus::Maintenance;
    }
}
