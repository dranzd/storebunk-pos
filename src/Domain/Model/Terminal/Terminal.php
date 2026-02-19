<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRoot;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRootTrait;
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
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalStatus;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

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
        if ($this->status->isDecommissioned()) {
            throw InvariantViolationException::withMessage('Cannot activate a decommissioned terminal');
        }

        $this->recordThat(
            TerminalActivated::occur($this->terminalId, new DateTimeImmutable())
        );
    }

    final public function disable(): void
    {
        if ($this->status->isDecommissioned()) {
            throw InvariantViolationException::withMessage('Cannot disable a decommissioned terminal');
        }

        $this->recordThat(
            TerminalDisabled::occur($this->terminalId, new DateTimeImmutable())
        );
    }

    final public function setMaintenance(): void
    {
        if ($this->status->isDecommissioned()) {
            throw InvariantViolationException::withMessage('Cannot set a decommissioned terminal to maintenance');
        }

        $this->recordThat(
            TerminalMaintenanceSet::occur($this->terminalId, new DateTimeImmutable())
        );
    }

    final public function decommission(string $reason): void
    {
        if ($this->status->isDecommissioned()) {
            throw InvariantViolationException::withMessage('Terminal is already decommissioned');
        }

        if ($this->status->isActive()) {
            throw InvariantViolationException::withMessage(
                'Cannot decommission an active terminal; disable or set to maintenance first'
            );
        }

        $this->recordThat(
            TerminalDecommissioned::occur($this->terminalId, $reason, new DateTimeImmutable())
        );
    }

    final public function recommission(string $reason): void
    {
        if (!$this->status->isDecommissioned()) {
            throw InvariantViolationException::withMessage('Terminal is not decommissioned');
        }

        $this->recordThat(
            TerminalRecommissioned::occur($this->terminalId, $reason, new DateTimeImmutable())
        );
    }

    final public function rename(string $newName): void
    {
        if ($this->status->isDecommissioned()) {
            throw InvariantViolationException::withMessage('Cannot rename a decommissioned terminal');
        }

        if ($this->name === $newName) {
            throw InvariantViolationException::withMessage('New name is the same as the current name');
        }

        $this->recordThat(
            TerminalRenamed::occur($this->terminalId, $this->name, $newName, new DateTimeImmutable())
        );
    }

    final public function reassign(BranchId $newBranchId): void
    {
        if ($this->status->isDecommissioned()) {
            throw InvariantViolationException::withMessage('Cannot reassign a decommissioned terminal');
        }

        if ($this->status->isActive()) {
            throw InvariantViolationException::withMessage(
                'Cannot reassign an active terminal; disable or set to maintenance first'
            );
        }

        if ($this->branchId->sameValueAs($newBranchId)) {
            throw InvariantViolationException::withMessage('New branch is the same as the current branch');
        }

        $this->recordThat(
            TerminalReassigned::occur($this->terminalId, $this->branchId, $newBranchId, new DateTimeImmutable())
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

    private function applyOnTerminalRenamed(TerminalRenamed $event): void
    {
        $this->name = $event->newName();
    }

    private function applyOnTerminalReassigned(TerminalReassigned $event): void
    {
        $this->branchId = $event->newBranchId();
    }

    private function applyOnTerminalDecommissioned(TerminalDecommissioned $event): void
    {
        $this->status = TerminalStatus::Decommissioned;
    }

    private function applyOnTerminalRecommissioned(TerminalRecommissioned $event): void
    {
        $this->status = TerminalStatus::Disabled;
    }
}
