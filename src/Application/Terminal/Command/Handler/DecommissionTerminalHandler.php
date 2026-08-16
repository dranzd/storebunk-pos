<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use Dranzd\StorebunkPos\Application\Terminal\Command\DecommissionTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

/**
 * DecommissionTerminalHandler
 *
 * Handles the DecommissionTerminal command by decommissioning the terminal aggregate.
 */
final class DecommissionTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    public function __invoke(DecommissionTerminal $command): void
    {
        $terminal = $this->terminalRepository->load(TerminalId::fromNative($command->terminalId));
        $terminal->decommission($command->reason);
        $this->terminalRepository->store($terminal);
    }
}
