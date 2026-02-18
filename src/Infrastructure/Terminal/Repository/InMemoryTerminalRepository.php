<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Infrastructure\Terminal\Repository;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Repository\TerminalRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Terminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;

final class InMemoryTerminalRepository implements TerminalRepositoryInterface
{
    public function __construct(
        private readonly InMemoryEventStore $eventStore
    ) {
    }

    final public function store(Terminal $terminal): void
    {
        $events = $terminal->popRecordedEvents();
        $this->eventStore->appendAll($events);
    }

    final public function load(TerminalId $terminalId): Terminal
    {
        $aggregateId = $terminalId->toNative();

        if (!$this->eventStore->hasEvents($aggregateId)) {
            throw AggregateNotFoundException::withId($aggregateId, 'Terminal');
        }

        $events = $this->eventStore->loadEvents($aggregateId);

        $terminal = new Terminal();
        return $terminal->reconstituteFromHistory($events);
    }
}
