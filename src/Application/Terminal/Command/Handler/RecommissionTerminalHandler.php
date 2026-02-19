<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use Dranzd\StorebunkPos\Application\Terminal\Command\RecommissionTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;

final class RecommissionTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    final public function __invoke(RecommissionTerminal $command): void
    {
        $terminal = $this->terminalRepository->load($command->terminalId());
        $terminal->recommission($command->reason());
        $this->terminalRepository->store($terminal);
    }
}
