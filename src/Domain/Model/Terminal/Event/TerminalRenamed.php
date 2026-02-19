<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalRenamed extends AbstractAggregateEvent implements DomainEventInterface
{
    private TerminalId $terminalId;
    private string $oldName;
    private string $newName;
    private DateTimeImmutable $renamedAt;

    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->terminalId = TerminalId::fromNative($array['payload']['terminal_id']);
        $event->oldName = $array['payload']['old_name'];
        $event->newName = $array['payload']['new_name'];
        $event->renamedAt = new DateTimeImmutable($array['payload']['renamed_at']);

        return $event;
    }

    final public static function occur(
        TerminalId $terminalId,
        string $oldName,
        string $newName,
        DateTimeImmutable $renamedAt
    ): self {
        $event = new self();
        $event->terminalId = $terminalId;
        $event->oldName = $oldName;
        $event->newName = $newName;
        $event->renamedAt = $renamedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.renamed';
    }

    final public function toArray(): array
    {
        return [
            'terminal_id' => $this->terminalId->toNative(),
            'old_name' => $this->oldName,
            'new_name' => $this->newName,
            'renamed_at' => $this->renamedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->renamedAt;
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function oldName(): string
    {
        return $this->oldName;
    }

    final public function newName(): string
    {
        return $this->newName;
    }

    final public function renamedAt(): DateTimeImmutable
    {
        return $this->renamedAt;
    }
}
