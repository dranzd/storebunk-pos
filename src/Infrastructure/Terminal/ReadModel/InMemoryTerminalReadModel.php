<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Infrastructure\Terminal\ReadModel;

use Dranzd\StorebunkPos\Application\Terminal\ReadModel\TerminalReadModelInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalActivated;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDisabled;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalMaintenanceSet;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;

final class InMemoryTerminalReadModel implements TerminalReadModelInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $terminals = [];

    final public function onTerminalRegistered(TerminalRegistered $event): void
    {
        $this->terminals[$event->terminalId()->toNative()] = [
            'terminal_id' => $event->terminalId()->toNative(),
            'branch_id' => $event->branchId()->toNative(),
            'name' => $event->name(),
            'status' => 'active',
            'registered_at' => $event->registeredAt()->format(DATE_ATOM),
        ];
    }

    final public function onTerminalActivated(TerminalActivated $event): void
    {
        $terminalId = $event->terminalId()->toNative();
        if (isset($this->terminals[$terminalId])) {
            $this->terminals[$terminalId]['status'] = 'active';
        }
    }

    final public function onTerminalDisabled(TerminalDisabled $event): void
    {
        $terminalId = $event->terminalId()->toNative();
        if (isset($this->terminals[$terminalId])) {
            $this->terminals[$terminalId]['status'] = 'disabled';
        }
    }

    final public function onTerminalMaintenanceSet(TerminalMaintenanceSet $event): void
    {
        $terminalId = $event->terminalId()->toNative();
        if (isset($this->terminals[$terminalId])) {
            $this->terminals[$terminalId]['status'] = 'maintenance';
        }
    }

    final public function getTerminal(string $terminalId): ?array
    {
        return $this->terminals[$terminalId] ?? null;
    }

    final public function getAllTerminals(): array
    {
        return array_values($this->terminals);
    }

    final public function getTerminalsByBranch(string $branchId): array
    {
        return array_values(
            array_filter(
                $this->terminals,
                fn(array $terminal) => $terminal['branch_id'] === $branchId
            )
        );
    }

    final public function getTerminalsByStatus(string $status): array
    {
        return array_values(
            array_filter(
                $this->terminals,
                fn(array $terminal) => $terminal['status'] === $status
            )
        );
    }
}
