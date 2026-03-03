<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class ActivateTerminal extends AbstractCommand
{
    private function __construct(
        private readonly string $terminalId,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            [
                'terminal_id' => $this->terminalId,
            ]
        );
    }

    final public static function withId(
        string $terminalId,
        ?string $commandId = null
    ): self
    {
        return new self($terminalId, $commandId ?? '');
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.activate';
    }

    final public function terminalId(): TerminalId
    {
        return TerminalId::fromNative($this->terminalId);
    }
}
