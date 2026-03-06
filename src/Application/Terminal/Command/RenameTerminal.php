<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class RenameTerminal extends AbstractCommand
{
    private function __construct(
        private readonly string $terminalId,
        private readonly string $newName,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            [
                'terminal_id' => $this->terminalId,
                'new_name' => $this->newName,
            ]
        );
    }

    final public static function to(
        string $terminalId,
        string $newName,
        ?string $commandId = null
    ): self {
        return new self($terminalId, $newName, $commandId ?? '');
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.rename';
    }

    final public function terminalId(): TerminalId
    {
        return TerminalId::fromNative($this->terminalId);
    }

    final public function newName(): string
    {
        return $this->newName;
    }
}
