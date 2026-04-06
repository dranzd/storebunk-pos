<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalRenamed extends BaseAggregateEvent implements DomainEventInterface
{
    private TerminalId $terminalId;
    private string $oldName;
    private string $newName;
    private DateTimeImmutable $renamedAt;

    final public static function occur(
        TerminalId $terminalId,
        string $oldName,
        string $newName,
        DateTimeImmutable $renamedAt,
    ): self {
        $instance = new self();
        $instance->terminalId = $terminalId;
        $instance->oldName = $oldName;
        $instance->newName = $newName;
        $instance->renamedAt = $renamedAt;
        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.terminal.renamed";
    }

    final public function getPayload(): array
    {
        return [
            "terminal_id" => $this->terminalId->toNative(),
            "old_name" => $this->oldName,
            "new_name" => $this->newName,
            "renamed_at" => $this->renamedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->terminalId = TerminalId::fromNative($payload["terminal_id"]);
        $this->oldName = $payload["old_name"];
        $this->newName = $payload["new_name"];
        $this->renamedAt = new DateTimeImmutable($payload["renamed_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->renamedAt;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getOldName(): string
    {
        return $this->oldName;
    }

    final public function getNewName(): string
    {
        return $this->newName;
    }

    final public function getRenamedAt(): DateTimeImmutable
    {
        return $this->renamedAt;
    }
}
