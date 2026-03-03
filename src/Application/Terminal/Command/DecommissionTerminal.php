<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class DecommissionTerminal extends AbstractCommand
{
    private function __construct(
        private readonly string $terminalId,
        private readonly string $reason
    ) {
        parent::__construct(
            $this->terminalId,
            self::expectedMessageName(),
            [
                'terminal_id' => $this->terminalId,
                'reason' => $this->reason,
            ]
        );
    }

    final public static function because(string $terminalId, string $reason): self
    {
        return new self($terminalId, $reason);
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
