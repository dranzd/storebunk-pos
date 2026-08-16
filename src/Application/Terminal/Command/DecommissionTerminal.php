<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * DecommissionTerminal
 *
 * Command to permanently decommission a terminal, with the reason recorded.
 */
final class DecommissionTerminal extends AbstractCommand
{
    public function __construct(
        public readonly string $terminalId,
        public readonly string $reason
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.decommission';
    }
}
