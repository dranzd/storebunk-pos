<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class RenameTerminal extends AbstractCommand
{
    private TerminalId $terminalId;
    private string $newName;

    public function __construct(TerminalId $terminalId, string $newName)
    {
        $this->terminalId = $terminalId;
        $this->newName = $newName;

        parent::__construct(
            $terminalId->toNative(),
            self::expectedMessageName(),
            [
                'terminal_id' => $terminalId->toNative(),
                'new_name' => $newName,
            ]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.rename';
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function newName(): string
    {
        return $this->newName;
    }
}
