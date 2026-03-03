<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class ReassignTerminal extends AbstractCommand
{
    private function __construct(
        private readonly string $terminalId,
        private readonly string $newBranchId
    ) {
        parent::__construct(
            $this->terminalId,
            self::expectedMessageName(),
            [
                'terminal_id' => $this->terminalId,
                'new_branch_id' => $this->newBranchId,
            ]
        );
    }

    final public static function toBranch(string $terminalId, string $newBranchId): self
    {
        return new self($terminalId, $newBranchId);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.reassign';
    }

    final public function terminalId(): TerminalId
    {
        return TerminalId::fromNative($this->terminalId);
    }

    final public function newBranchId(): BranchId
    {
        return BranchId::fromNative($this->newBranchId);
    }
}
