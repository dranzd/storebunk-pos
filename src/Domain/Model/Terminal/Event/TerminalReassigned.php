<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalReassigned extends BaseAggregateEvent implements
    DomainEventInterface
{
    private TerminalId $terminalId;
    private BranchId $oldBranchId;
    private BranchId $newBranchId;
    private DateTimeImmutable $reassignedAt;

    final public static function occur(
        TerminalId $terminalId,
        BranchId $oldBranchId,
        BranchId $newBranchId,
        DateTimeImmutable $reassignedAt,
    ): self {
        $instance = new self();
        $instance->terminalId = $terminalId;
        $instance->oldBranchId = $oldBranchId;
        $instance->newBranchId = $newBranchId;
        $instance->reassignedAt = $reassignedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.terminal.reassigned';
    }

    final public function getPayload(): array
    {
        return [
            'terminal_id' => $this->terminalId->toNative(),
            'old_branch_id' => $this->oldBranchId->toNative(),
            'new_branch_id' => $this->newBranchId->toNative(),
            'reassigned_at' => $this->reassignedAt->format(
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
        $this->oldBranchId = BranchId::fromNative($payload['old_branch_id']);
        $this->newBranchId = BranchId::fromNative($payload['new_branch_id']);
        $this->reassignedAt = new DateTimeImmutable($payload['reassigned_at']);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->reassignedAt;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getOldBranchId(): BranchId
    {
        return $this->oldBranchId;
    }

    final public function getNewBranchId(): BranchId
    {
        return $this->newBranchId;
    }

    final public function getReassignedAt(): DateTimeImmutable
    {
        return $this->reassignedAt;
    }
}
