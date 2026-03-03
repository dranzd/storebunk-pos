<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class SetTerminalMaintenance extends AbstractCommand
{
    private function __construct(
        private readonly string $terminalId
    ) {
        parent::__construct(
            $this->terminalId,
            self::expectedMessageName(),
            [
                'terminal_id' => $this->terminalId,
            ]
        );
    }

    final public static function forTerminal(string $terminalId): self
    {
        return new self($terminalId);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.set_maintenance';
    }

    final public function terminalId(): TerminalId
    {
        return TerminalId::fromString($this->terminalId);
    }
}
