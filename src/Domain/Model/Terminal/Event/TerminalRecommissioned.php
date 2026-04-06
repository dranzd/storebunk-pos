<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalRecommissioned extends BaseAggregateEvent implements
    DomainEventInterface
{
    private TerminalId $terminalId;
    private DateTimeImmutable $recommissionedAt;

    final public static function occur(
        TerminalId $terminalId,
        DateTimeImmutable $recommissionedAt,
    ): self {
        $instance = new self();
        $instance->terminalId = $terminalId;
        $instance->recommissionedAt = $recommissionedAt;
        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.terminal.recommissioned";
    }

    final public function getPayload(): array
    {
        return [
            "terminal_id" => $this->terminalId->toNative(),
            "recommissioned_at" => $this->recommissionedAt->format(
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
        $this->recommissionedAt = new DateTimeImmutable(
            $payload["recommissioned_at"],
        );
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->recommissionedAt;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getRecommissionedAt(): DateTimeImmutable
    {
        return $this->recommissionedAt;
    }
}
