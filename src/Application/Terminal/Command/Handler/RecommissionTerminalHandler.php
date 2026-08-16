<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use Dranzd\StorebunkPos\Application\Terminal\Command\RecommissionTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

/**
 * RecommissionTerminalHandler
 *
 * Handles the RecommissionTerminal command by bringing the terminal back into service.
 */
final class RecommissionTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    public function __invoke(RecommissionTerminal $command): void
    {
        $terminal = $this->terminalRepository->load(TerminalId::fromNative($command->terminalId));
        $terminal->recommission($command->reason);
        $this->terminalRepository->store($terminal);
    }
}
