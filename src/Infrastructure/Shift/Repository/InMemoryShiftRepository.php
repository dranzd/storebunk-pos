<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Infrastructure\Shift\Repository;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;

final class InMemoryShiftRepository implements ShiftRepositoryInterface
{
    public function __construct(
        private readonly InMemoryEventStore $eventStore
    ) {
    }

    final public function store(Shift $shift): void
    {
        $events = $shift->popRecordedEvents();
        $this->eventStore->appendAll($events);
    }

    final public function load(ShiftId $shiftId): Shift
    {
        $aggregateId = $shiftId->toNative();

        if (!$this->eventStore->hasEvents($aggregateId)) {
            throw AggregateNotFoundException::withId($aggregateId, 'Shift');
        }

        $events = $this->eventStore->loadEvents($aggregateId);

        $shift = new Shift();
        return $shift->reconstituteFromHistory($events);
    }
}
