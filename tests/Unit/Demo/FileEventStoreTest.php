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
        // tempnam() creates the placeholder file it returns; reuse that exact
        // path so nothing is left behind in the temp dir after tearDown.
        $this->filePath = tempnam(sys_get_temp_dir(), 'pos-demo-events-');
    }

    protected function tearDown(): void
    {
        foreach ([$this->filePath, $this->filePath . '.lock', $this->filePath . '.tmp'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
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

    public function test_a_corrupt_store_file_is_never_overwritten(): void
    {
        $store = new FileEventStore($this->filePath);
        file_put_contents($this->filePath, '{"torn write');

        try {
            $store->append($this->terminalRegistered('agg-1'));
            $this->fail('Expected a RuntimeException for the corrupt store file');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('not valid JSON', $exception->getMessage());
        }

        $this->assertSame('{"torn write', file_get_contents($this->filePath));
    }

    public function test_an_unwritable_store_location_fails_loudly(): void
    {
        $this->skipIfRunningAsRoot();

        $dir = $this->filePath . '-dir';
        mkdir($dir, 0o500);
        $store = new FileEventStore($dir . '/events.json');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('event NOT persisted');

            $store->append($this->terminalRegistered('agg-1'));
        } finally {
            chmod($dir, 0o700);
            rmdir($dir);
        }
    }

    public function test_a_failed_write_leaves_existing_history_untouched(): void
    {
        $this->skipIfRunningAsRoot();

        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1'));
        $persisted = file_get_contents($this->filePath);

        // Make the directory read-only so the temp-file write fails; the
        // store must throw and the previously persisted history must survive
        // byte-for-byte (no in-place truncation).
        $dir = $this->filePath . '-dir';
        mkdir($dir, 0o700);
        $path = $dir . '/events.json';
        copy($this->filePath, $path);
        // Pre-create the lock sidecar so only the temp-file write (not the
        // lock open) hits the read-only directory.
        touch($path . '.lock');
        $guarded = new FileEventStore($path);
        chmod($dir, 0o500);

        try {
            $guarded->append($this->terminalRegistered('agg-2'));
            $this->fail('Expected a RuntimeException for the failed write');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('existing history left untouched', $exception->getMessage());
        } finally {
            chmod($dir, 0o700);
        }

        try {
            $this->assertSame($persisted, file_get_contents($path));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    public function test_an_existing_unreadable_store_is_not_replaced(): void
    {
        $this->skipIfRunningAsRoot();

        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1'));
        $persisted = file_get_contents($this->filePath);

        // The store becomes unreadable AFTER this process loaded it — e.g. a
        // permission change between two demo commands. Saving must refuse to
        // replace it rather than treat it as empty and erase agg-1's history.
        chmod($this->filePath, 0o000);

        try {
            $store->append($this->terminalRegistered('agg-2'));
            $this->fail('Expected a RuntimeException for the unreadable store file');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('could not be read', $exception->getMessage());
        } finally {
            chmod($this->filePath, 0o600);
        }

        $this->assertSame($persisted, file_get_contents($this->filePath));
    }

    public function test_loading_an_existing_unreadable_store_fails_loudly(): void
    {
        $this->skipIfRunningAsRoot();

        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1'));
        chmod($this->filePath, 0o000);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('could not be read');

            new FileEventStore($this->filePath);
        } finally {
            chmod($this->filePath, 0o600);
        }
    }

    private function skipIfRunningAsRoot(): void
    {
        // Root ignores directory permission bits, so the read-only-directory
        // failure injection below cannot work.
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('Permission-based failure injection does not work as root.');
        }
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
