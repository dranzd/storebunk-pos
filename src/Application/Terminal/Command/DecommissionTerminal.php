<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class DecommissionTerminal extends AbstractCommand
{
    private function __construct(
        private readonly string $terminalId,
        private readonly string $reason,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            [
                'terminal_id' => $this->terminalId,
                'reason' => $this->reason,
            ]
        );
    }

    final public static function because(
        string $terminalId,
        string $reason,
        ?string $commandId = null
    ): self
    {
        return new self(
            $terminalId,
            $reason,
            $commandId ?? ''
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.decommission';
    }

    final public function terminalId(): TerminalId
    {
        return TerminalId::fromNative($this->terminalId);
    }

    final public function reason(): string
    {
        return $this->reason;
    }
}
