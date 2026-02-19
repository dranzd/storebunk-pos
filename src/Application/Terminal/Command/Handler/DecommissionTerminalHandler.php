<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use Dranzd\StorebunkPos\Application\Terminal\Command\DecommissionTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;

final class DecommissionTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    final public function __invoke(DecommissionTerminal $command): void
    {
        $terminal = $this->terminalRepository->load($command->terminalId());
        $terminal->decommission($command->reason());
        $this->terminalRepository->store($terminal);
    }
}
