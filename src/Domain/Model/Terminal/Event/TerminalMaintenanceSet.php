<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalMaintenanceSet extends AbstractAggregateEvent implements DomainEventInterface
{
    private TerminalId $terminalId;
    private DateTimeImmutable $maintenanceSetAt;

    final public static function occur(
        TerminalId $terminalId,
        DateTimeImmutable $maintenanceSetAt
    ): self {
        $event = new self();
        $event->terminalId = $terminalId;
        $event->maintenanceSetAt = $maintenanceSetAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.maintenance_set';
    }

    final public function toArray(): array
    {
        return [
            'terminal_id' => $this->terminalId->toNative(),
            'maintenance_set_at' => $this->maintenanceSetAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->maintenanceSetAt;
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function maintenanceSetAt(): DateTimeImmutable
    {
        return $this->maintenanceSetAt;
    }
}
