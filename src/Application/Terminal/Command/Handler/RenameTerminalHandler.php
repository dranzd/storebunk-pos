<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use Dranzd\StorebunkPos\Application\Terminal\Command\RenameTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

/**
 * RenameTerminalHandler
 *
 * Handles the RenameTerminal command by renaming the terminal aggregate.
 */
final class RenameTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    public function __invoke(RenameTerminal $command): void
    {
        $terminal = $this->terminalRepository->load(TerminalId::fromNative($command->terminalId));
        $terminal->rename($command->newName);
        $this->terminalRepository->store($terminal);
    }
}
