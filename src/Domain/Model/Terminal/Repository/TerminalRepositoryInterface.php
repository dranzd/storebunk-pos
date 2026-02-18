<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Repository;

use Dranzd\StorebunkPos\Domain\Model\Terminal\Terminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

interface TerminalRepositoryInterface
{
    public function store(Terminal $terminal, ?int $expectedVersion = null): void;

    public function load(TerminalId $terminalId): Terminal;
}
