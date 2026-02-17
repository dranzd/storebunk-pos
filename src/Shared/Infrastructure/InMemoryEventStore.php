<?php

declare(strict_types=1);

namespace Storebunk\Shared\Infrastructure;

use Storebunk\Shared\Domain\DomainEvent;

class InMemoryEventStore
{
    /** @var array<string, DomainEvent[]> */
    private array $events = [];

    public function save(string $aggregateId, array $events): void
    {
        if (!isset($this->events[$aggregateId])) {
            $this->events[$aggregateId] = [];
        }

        foreach ($events as $event) {
            $this->events[$aggregateId][] = $event;
        }
    }

    /** @return DomainEvent[] */
    public function getEventsFor(string $aggregateId): array
    {
        return $this->events[$aggregateId] ?? [];
    }
}
