<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * SetTerminalMaintenance
 *
 * Command to place a terminal into maintenance mode.
 */
final class SetTerminalMaintenance extends AbstractCommand
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
        return 'storebunk.pos.terminal.set_maintenance';
    }
}
