<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * RenameTerminal
 *
 * Command to rename a terminal.
 */
final class RenameTerminal extends AbstractCommand
{
    public function __construct(
        public readonly string $terminalId,
        public readonly string $newName
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.rename';
    }
}
