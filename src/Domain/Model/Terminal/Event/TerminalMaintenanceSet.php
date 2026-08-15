<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalMaintenanceSet extends BaseAggregateEvent implements
    DomainEventInterface
{
    private TerminalId $terminalId;
    private DateTimeImmutable $maintenanceSetAt;

    final public static function occur(
        TerminalId $terminalId,
        DateTimeImmutable $maintenanceSetAt,
    ): self {
        $instance = new self();
        $instance->terminalId = $terminalId;
        $instance->maintenanceSetAt = $maintenanceSetAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.maintenance_set';
    }

    final public function getPayload(): array
    {
        return [
            'terminal_id' => $this->terminalId->toNative(),
            'maintenance_set_at' => $this->maintenanceSetAt->format(
                \DateTimeInterface::ATOM,
            ),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->terminalId = TerminalId::fromNative($payload['terminal_id']);
        $this->maintenanceSetAt = new DateTimeImmutable(
            $payload['maintenance_set_at'],
        );
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->maintenanceSetAt;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getMaintenanceSetAt(): DateTimeImmutable
    {
        return $this->maintenanceSetAt;
    }
}
