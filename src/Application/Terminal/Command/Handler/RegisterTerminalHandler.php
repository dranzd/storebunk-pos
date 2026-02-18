<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Terminal;

final class RegisterTerminalHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    final public function __invoke(RegisterTerminal $command): void
    {
        $terminal = Terminal::register(
            $command->terminalId(),
            $command->branchId(),
            $command->name()
        );

        $this->terminalRepository->store($terminal);
    }
}
