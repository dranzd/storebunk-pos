<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Terminal\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * RecommissionTerminal
 *
 * Command to bring a decommissioned terminal back into service, with the reason recorded.
 */
final class RecommissionTerminal extends AbstractCommand
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
        return 'storebunk.pos.terminal.recommission';
    }
}
