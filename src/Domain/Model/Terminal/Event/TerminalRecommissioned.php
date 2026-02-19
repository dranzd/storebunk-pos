<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalRecommissioned extends AbstractAggregateEvent implements DomainEventInterface
{
    private TerminalId $terminalId;
    private string $reason;
    private DateTimeImmutable $recommissionedAt;

    /**
     * @param array<string, mixed> $array
     */
    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->terminalId = TerminalId::fromNative($array['payload']['terminal_id']);
        $event->reason = $array['payload']['reason'];
        $event->recommissionedAt = new DateTimeImmutable($array['payload']['recommissioned_at']);

        return $event;
    }

    final public static function occur(
        TerminalId $terminalId,
        string $reason,
        DateTimeImmutable $recommissionedAt
    ): self {
        $event = new self();
        $event->terminalId = $terminalId;
        $event->reason = $reason;
        $event->recommissionedAt = $recommissionedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.recommissioned';
    }

    final public function toArray(): array
    {
        return [
            'terminal_id' => $this->terminalId->toNative(),
            'reason' => $this->reason,
            'recommissioned_at' => $this->recommissionedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->recommissionedAt;
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function reason(): string
    {
        return $this->reason;
    }

    final public function recommissionedAt(): DateTimeImmutable
    {
        return $this->recommissionedAt;
    }
}
