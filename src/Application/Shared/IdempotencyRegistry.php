<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shared;

final class IdempotencyRegistry
{
    /** @var array<string, true> */
    private array $processedCommandIds = [];

    public function hasBeenProcessed(string $commandId): bool
    {
        return isset($this->processedCommandIds[$commandId]);
    }

    public function markAsProcessed(string $commandId): void
    {
        $this->processedCommandIds[$commandId] = true;
    }
}
