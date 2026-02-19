<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalActivated extends AbstractAggregateEvent implements DomainEventInterface
{
    private TerminalId $terminalId;
    private DateTimeImmutable $activatedAt;

    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->terminalId = TerminalId::fromNative($array['payload']['terminal_id']);
        $event->activatedAt = new DateTimeImmutable($array['payload']['activated_at']);

        return $event;
    }

    final public static function occur(
        TerminalId $terminalId,
        DateTimeImmutable $activatedAt
    ): self {
        $event = new self();
        $event->terminalId = $terminalId;
        $event->activatedAt = $activatedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.activated';
    }

    final public function toArray(): array
    {
        return [
            'terminal_id' => $this->terminalId->toNative(),
            'activated_at' => $this->activatedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->activatedAt;
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function activatedAt(): DateTimeImmutable
    {
        return $this->activatedAt;
    }
}
