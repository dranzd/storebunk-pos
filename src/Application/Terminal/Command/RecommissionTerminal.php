<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class RecommissionTerminal extends AbstractCommand
{
    private TerminalId $terminalId;
    private string $reason;

    public function __construct(TerminalId $terminalId, string $reason)
    {
        $this->terminalId = $terminalId;
        $this->reason = $reason;

        parent::__construct(
            $terminalId->toNative(),
            self::expectedMessageName(),
            [
                'terminal_id' => $terminalId->toNative(),
                'reason' => $reason,
            ]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.recommission';
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function reason(): string
    {
        return $this->reason;
    }
}
