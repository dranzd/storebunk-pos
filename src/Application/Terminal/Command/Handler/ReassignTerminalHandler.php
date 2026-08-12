<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use Dranzd\StorebunkPos\Application\Terminal\Command\ReassignTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

/**
 * ReassignTerminalHandler
 *
 * Handles the ReassignTerminal command by moving the terminal to a new branch.
 */
final class ReassignTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    public function __invoke(ReassignTerminal $command): void
    {
        $terminal = $this->terminalRepository->load(TerminalId::fromNative($command->terminalId));
        $terminal->reassign(BranchId::fromNative($command->newBranchId));
        $this->terminalRepository->store($terminal);
    }
}
