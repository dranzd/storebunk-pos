<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Application\Terminal\Command\ActivateTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;

final class ActivateTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    final public function __invoke(ActivateTerminal $command): void
    {
        $terminal = $this->terminalRepository->load($command->terminalId());
        $terminal->activate();
        $this->terminalRepository->store($terminal);
    }
}
