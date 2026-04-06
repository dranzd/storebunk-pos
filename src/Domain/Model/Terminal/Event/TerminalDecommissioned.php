<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalDecommissioned extends BaseAggregateEvent implements
    DomainEventInterface
{
    private TerminalId $terminalId;
    private DateTimeImmutable $decommissionedAt;

    final public static function occur(
        TerminalId $terminalId,
        DateTimeImmutable $decommissionedAt,
    ): self {
        $instance = new self();
        $instance->terminalId = $terminalId;
        $instance->decommissionedAt = $decommissionedAt;
        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.terminal.decommissioned";
    }

    final public function getPayload(): array
    {
        return [
            "terminal_id" => $this->terminalId->toNative(),
            "decommissioned_at" => $this->decommissionedAt->format(
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
        $this->decommissionedAt = new DateTimeImmutable(
            $payload["decommissioned_at"],
        );
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->decommissionedAt;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getDecommissionedAt(): DateTimeImmutable
    {
        return $this->decommissionedAt;
    }
}
