<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * RegisterTerminal
 *
 * Command to register a new terminal at a branch.
 */
final class RegisterTerminal extends AbstractCommand
{
    public function __construct(
        public readonly string $terminalId,
        public readonly string $branchId,
        public readonly string $name
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.register';
    }
}
