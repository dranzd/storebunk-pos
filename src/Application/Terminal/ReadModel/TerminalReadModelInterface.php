<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\ReadModel;

interface TerminalReadModelInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function getTerminal(string $terminalId): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllTerminals(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTerminalsByBranch(string $branchId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTerminalsByStatus(string $status): array;
}
