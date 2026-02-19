<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalRegistered extends AbstractAggregateEvent implements DomainEventInterface
{
    private TerminalId $terminalId;
    private BranchId $branchId;
    private string $name;
    private DateTimeImmutable $registeredAt;

    /**
     * @param array<string, mixed> $array
     */
    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->terminalId = TerminalId::fromNative($array['payload']['terminal_id']);
        $event->branchId = BranchId::fromNative($array['payload']['branch_id']);
        $event->name = $array['payload']['name'];
        $event->registeredAt = new DateTimeImmutable($array['payload']['registered_at']);

        return $event;
    }

    final public static function occur(
        TerminalId $terminalId,
        BranchId $branchId,
        string $name,
        DateTimeImmutable $registeredAt
    ): self {
        $event = new self();
        $event->terminalId = $terminalId;
        $event->branchId = $branchId;
        $event->name = $name;
        $event->registeredAt = $registeredAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.registered';
    }

    final public function toArray(): array
    {
        return [
            'terminal_id' => $this->terminalId->toNative(),
            'branch_id' => $this->branchId->toNative(),
            'name' => $this->name,
            'registered_at' => $this->registeredAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function branchId(): BranchId
    {
        return $this->branchId;
    }

    final public function name(): string
    {
        return $this->name;
    }

    final public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }
}
