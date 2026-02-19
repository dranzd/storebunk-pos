<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalReassigned extends AbstractAggregateEvent implements DomainEventInterface
{
    private TerminalId $terminalId;
    private BranchId $oldBranchId;
    private BranchId $newBranchId;
    private DateTimeImmutable $reassignedAt;

    /**
     * @param array<string, mixed> $array
     */
    final public static function fromArray(array $array): static
    {
        $event = parent::fromArray($array);
        $event->terminalId = TerminalId::fromNative($array['payload']['terminal_id']);
        $event->oldBranchId = BranchId::fromNative($array['payload']['old_branch_id']);
        $event->newBranchId = BranchId::fromNative($array['payload']['new_branch_id']);
        $event->reassignedAt = new DateTimeImmutable($array['payload']['reassigned_at']);

        return $event;
    }

    final public static function occur(
        TerminalId $terminalId,
        BranchId $oldBranchId,
        BranchId $newBranchId,
        DateTimeImmutable $reassignedAt
    ): self {
        $event = new self();
        $event->terminalId = $terminalId;
        $event->oldBranchId = $oldBranchId;
        $event->newBranchId = $newBranchId;
        $event->reassignedAt = $reassignedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.reassigned';
    }

    final public function toArray(): array
    {
        return [
            'terminal_id' => $this->terminalId->toNative(),
            'old_branch_id' => $this->oldBranchId->toNative(),
            'new_branch_id' => $this->newBranchId->toNative(),
            'reassigned_at' => $this->reassignedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->reassignedAt;
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function oldBranchId(): BranchId
    {
        return $this->oldBranchId;
    }

    final public function newBranchId(): BranchId
    {
        return $this->newBranchId;
    }

    final public function reassignedAt(): DateTimeImmutable
    {
        return $this->reassignedAt;
    }
}
