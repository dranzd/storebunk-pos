<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalDisabled extends AbstractAggregateEvent implements DomainEventInterface
{
    private TerminalId $terminalId;
    private DateTimeImmutable $disabledAt;

    final public static function occur(
        TerminalId $terminalId,
        DateTimeImmutable $disabledAt
    ): self {
        $event = new self();
        $event->terminalId = $terminalId;
        $event->disabledAt = $disabledAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.disabled';
    }

    final public function toArray(): array
    {
        return [
            'terminal_id' => $this->terminalId->toNative(),
            'disabled_at' => $this->disabledAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->disabledAt;
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function disabledAt(): DateTimeImmutable
    {
        return $this->disabledAt;
    }
}
