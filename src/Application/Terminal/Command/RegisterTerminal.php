<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class RegisterTerminal extends AbstractCommand
{
    private function __construct(
        private readonly string $terminalId,
        private readonly string $branchId,
        private readonly string $name
    ) {
        parent::__construct(
            $this->terminalId,
            self::expectedMessageName(),
            [
                'terminal_id' => $this->terminalId,
                'branch_id' => $this->branchId,
                'name' => $this->name,
            ]
        );
    }

    final public static function register(string $terminalId, string $branchId, string $name): self
    {
        return new self($terminalId, $branchId, $name);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.register';
    }

    final public function terminalId(): TerminalId
    {
        return TerminalId::fromNative($this->terminalId);
    }

    final public function branchId(): BranchId
    {
        return BranchId::fromNative($this->branchId);
    }

    final public function name(): string
    {
        return $this->name;
    }
}
