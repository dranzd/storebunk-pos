<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use Dranzd\StorebunkPos\Application\Terminal\Command\ReassignTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;

final class ReassignTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    final public function __invoke(ReassignTerminal $command): void
    {
        $terminal = $this->terminalRepository->load($command->terminalId());
        $terminal->reassign($command->newBranchId());
        $this->terminalRepository->store($terminal);
    }
}
