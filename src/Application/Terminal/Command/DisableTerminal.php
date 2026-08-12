<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * DisableTerminal
 *
 * Command to disable an active terminal.
 */
final class DisableTerminal extends AbstractCommand
{
    public function __construct(
        public readonly string $terminalId
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.disable';
    }
}
