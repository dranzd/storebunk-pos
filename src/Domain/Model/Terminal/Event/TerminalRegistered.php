<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalRegistered extends BaseAggregateEvent implements
    DomainEventInterface
{
    private TerminalId $terminalId;
    private BranchId $branchId;
    private string $name;
    private DateTimeImmutable $registeredAt;

    final public static function occur(
        TerminalId $terminalId,
        BranchId $branchId,
        string $name,
        DateTimeImmutable $registeredAt,
    ): self {
        $instance = new self();
        $instance->terminalId = $terminalId;
        $instance->branchId = $branchId;
        $instance->name = $name;
        $instance->registeredAt = $registeredAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.terminal.registered";
    }

    final public function getPayload(): array
    {
        return [
            "terminal_id" => $this->terminalId->toNative(),
            "branch_id" => $this->branchId->toNative(),
            "name" => $this->name,
            "registered_at" => $this->registeredAt->format(
                \DateTimeInterface::ATOM,
            ),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->terminalId = TerminalId::fromNative($payload["terminal_id"]);
        $this->branchId = BranchId::fromNative($payload["branch_id"]);
        $this->name = $payload["name"];
        $this->registeredAt = new DateTimeImmutable($payload["registered_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getBranchId(): BranchId
    {
        return $this->branchId;
    }

    final public function getName(): string
    {
        return $this->name;
    }

    final public function getRegisteredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }
}
