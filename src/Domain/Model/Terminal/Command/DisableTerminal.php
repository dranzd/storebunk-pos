<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class DisableTerminal extends AbstractCommand
{
    private TerminalId $terminalId;

    public function __construct(TerminalId $terminalId)
    {
        $this->terminalId = $terminalId;

        parent::__construct(
            $terminalId->toNative(),
            self::expectedMessageName(),
            [
                'terminal_id' => $terminalId->toNative(),
            ]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.disable';
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }
}
