<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalActivated extends BaseAggregateEvent implements
    DomainEventInterface
{
    private TerminalId $terminalId;
    private DateTimeImmutable $activatedAt;

    final public static function occur(
        TerminalId $terminalId,
        DateTimeImmutable $activatedAt,
    ): self {
        $instance = new self();
        $instance->terminalId = $terminalId;
        $instance->activatedAt = $activatedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.terminal.activated";
    }

    final public function getPayload(): array
    {
        return [
            "terminal_id" => $this->terminalId->toNative(),
            "activated_at" => $this->activatedAt->format(
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
        $this->activatedAt = new DateTimeImmutable($payload["activated_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->activatedAt;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getActivatedAt(): DateTimeImmutable
    {
        return $this->activatedAt;
    }
}
