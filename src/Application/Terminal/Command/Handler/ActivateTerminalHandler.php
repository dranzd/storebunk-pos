<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use Dranzd\StorebunkPos\Application\Terminal\Command\ActivateTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

/**
 * ActivateTerminalHandler
 *
 * Handles the ActivateTerminal command by activating the terminal aggregate.
 */
final class ActivateTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    public function __invoke(ActivateTerminal $command): void
    {
        $terminal = $this->terminalRepository->load(TerminalId::fromNative($command->terminalId));
        $terminal->activate();
        $this->terminalRepository->store($terminal);
    }
}
