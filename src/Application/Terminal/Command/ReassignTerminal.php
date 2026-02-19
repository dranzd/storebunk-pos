<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class ReassignTerminal extends AbstractCommand
{
    private TerminalId $terminalId;
    private BranchId $newBranchId;

    public function __construct(TerminalId $terminalId, BranchId $newBranchId)
    {
        $this->terminalId = $terminalId;
        $this->newBranchId = $newBranchId;

        parent::__construct(
            $terminalId->toNative(),
            self::expectedMessageName(),
            [
                'terminal_id' => $terminalId->toNative(),
                'new_branch_id' => $newBranchId->toNative(),
            ]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.reassign';
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function newBranchId(): BranchId
    {
        return $this->newBranchId;
    }
}
