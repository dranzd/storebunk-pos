<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Demo;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateEvent;
use Dranzd\StorebunkPos\Demo\Cli\FileEventStore;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use PHPUnit\Framework\TestCase;

final class FileEventStoreTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        $this->filePath = tempnam(sys_get_temp_dir(), 'pos-demo-events-') . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function test_events_survive_a_reload_round_trip(): void
    {
        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1'));

        $reloaded = new FileEventStore($this->filePath);

        $this->assertTrue($reloaded->hasEvents('agg-1'));
        $events = $reloaded->loadEvents('agg-1');
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminalRegistered::class, $events[0]);
        $this->assertSame('agg-1', $events[0]->getAggregateRootUuid());
    }

    public function test_concurrent_writers_do_not_lose_each_others_events(): void
    {
        // Both stores load the (empty) file BEFORE either writes — the shape
        // of two demo CLI processes running side by side.
        $storeA = new FileEventStore($this->filePath);
        $storeB = new FileEventStore($this->filePath);

        $storeB->append($this->terminalRegistered('agg-b'));
        // Before the merge-on-write fix, A's save() overwrote the file with
        // its stale construction-time snapshot, erasing B's event.
        $storeA->append($this->terminalRegistered('agg-a'));

        $reloaded = new FileEventStore($this->filePath);

        $this->assertTrue($reloaded->hasEvents('agg-a'));
        $this->assertTrue($reloaded->hasEvents('agg-b'));
    }

    private function terminalRegistered(string $aggregateRootUuid): AggregateEvent
    {
        return TerminalRegistered::occur(
            new TerminalId(),
            new BranchId(),
            'Demo Terminal',
            new DateTimeImmutable()
        )->withMetadata([
            AggregateEvent::META_AGGREGATE_ROOT_UUID    => $aggregateRootUuid,
            AggregateEvent::META_AGGREGATE_ROOT_TYPE    => 'Terminal',
            AggregateEvent::META_AGGREGATE_ROOT_VERSION => 1,
        ]);
    }
}
