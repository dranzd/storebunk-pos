<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * ReassignTerminal
 *
 * Command to reassign a terminal to a different branch.
 */
final class ReassignTerminal extends AbstractCommand
{
    public function __construct(
        public readonly string $terminalId,
        public readonly string $newBranchId
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.reassign';
    }
}
