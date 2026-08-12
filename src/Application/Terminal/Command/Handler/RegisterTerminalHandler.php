<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Terminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

/**
 * RegisterTerminalHandler
 *
 * Handles the RegisterTerminal command by creating a new Terminal aggregate.
 */
final class RegisterTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    public function __invoke(RegisterTerminal $command): void
    {
        $terminal = Terminal::register(
            TerminalId::fromNative($command->terminalId),
            BranchId::fromNative($command->branchId),
            $command->name
        );

        $this->terminalRepository->store($terminal);
    }
}
