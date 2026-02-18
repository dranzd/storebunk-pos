<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Infrastructure\PosSession\Repository;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Domain\Model\PosSession\PosSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;

final class InMemoryPosSessionRepository implements PosSessionRepositoryInterface
{
    public function __construct(
        private readonly InMemoryEventStore $eventStore
    ) {
    }

    final public function store(PosSession $session): void
    {
        $events = $session->popRecordedEvents();
        $this->eventStore->appendAll($events);
    }

    final public function load(SessionId $sessionId): PosSession
    {
        $aggregateId = $sessionId->toNative();

        if (!$this->eventStore->hasEvents($aggregateId)) {
            throw AggregateNotFoundException::withId($aggregateId, 'PosSession');
        }

        $events = $this->eventStore->loadEvents($aggregateId);

        $session = new PosSession();
        return $session->reconstituteFromHistory($events);
    }
}
