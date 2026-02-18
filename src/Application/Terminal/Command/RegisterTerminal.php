<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class RegisterTerminal extends AbstractCommand
{
    private TerminalId $terminalId;
    private BranchId $branchId;
    private string $name;

    public function __construct(
        TerminalId $terminalId,
        BranchId $branchId,
        string $name
    ) {
        $this->terminalId = $terminalId;
        $this->branchId = $branchId;
        $this->name = $name;

        parent::__construct(
            $terminalId->toNative(),
            self::expectedMessageName(),
            [
                'terminal_id' => $terminalId->toNative(),
                'branch_id' => $branchId->toNative(),
                'name' => $name,
            ]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.register';
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function branchId(): BranchId
    {
        return $this->branchId;
    }

    final public function name(): string
    {
        return $this->name;
    }
}
