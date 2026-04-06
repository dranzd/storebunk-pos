<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class TerminalDisabled extends BaseAggregateEvent implements
    DomainEventInterface
{
    private TerminalId $terminalId;
    private DateTimeImmutable $disabledAt;

    final public static function occur(
        TerminalId $terminalId,
        DateTimeImmutable $disabledAt,
    ): self {
        $instance = new self();
        $instance->terminalId = $terminalId;
        $instance->disabledAt = $disabledAt;
        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.terminal.disabled";
    }

    final public function getPayload(): array
    {
        return [
            "terminal_id" => $this->terminalId->toNative(),
            "disabled_at" => $this->disabledAt->format(
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
        $this->disabledAt = new DateTimeImmutable($payload["disabled_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->disabledAt;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getDisabledAt(): DateTimeImmutable
    {
        return $this->disabledAt;
    }
}
