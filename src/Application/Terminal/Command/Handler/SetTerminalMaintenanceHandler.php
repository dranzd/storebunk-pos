<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command\Handler;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Application\Terminal\Command\SetTerminalMaintenance;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;

final class SetTerminalMaintenanceHandler
{
    public function __construct(
        private readonly TerminalRepositoryInterface $terminalRepository
    ) {
    }

    final public function __invoke(SetTerminalMaintenance $command): void
    {
        $terminal = $this->terminalRepository->load($command->terminalId());
        $terminal->setMaintenance();
        $this->terminalRepository->store($terminal);
    }
}
