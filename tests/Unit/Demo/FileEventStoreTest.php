<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Demo;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateEvent;
use Dranzd\StorebunkPos\Demo\Cli\FileEventStore;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\ConcurrencyException;
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

    public function test_version_numbering_survives_a_reload_round_trip(): void
    {
        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1', 1));
        $store->append($this->terminalRegistered('agg-1', 2));
        $store->append($this->terminalRegistered('agg-1', 3));

        $reloaded = new FileEventStore($this->filePath);

        // A vendor serialization change that dropped or reordered the version
        // metadata would silently break optimistic concurrency downstream.
        $versions = array_map(
            fn (AggregateEvent $event): int => $event->getAggregateRootVersion(),
            $reloaded->loadEvents('agg-1')
        );
        $this->assertSame([1, 2, 3], $versions);

        $tail = $reloaded->loadEventsFromVersion('agg-1', 1);
        $this->assertSame(
            [2, 3],
            array_map(fn (AggregateEvent $event): int => $event->getAggregateRootVersion(), $tail)
        );
        $this->assertSame([], $reloaded->loadEventsFromVersion('agg-1', 3));
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

    public function test_loading_a_corrupt_store_file_fails_loudly(): void
    {
        file_put_contents($this->filePath, '{"torn write');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not valid JSON');

        new FileEventStore($this->filePath);
    }

    public function test_loading_an_unknown_event_class_fails_loudly(): void
    {
        file_put_contents($this->filePath, json_encode([
            'agg-1' => [
                ['class' => 'Vendor\\Gone\\RemovedEvent', 'data' => []],
            ],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('event class "Vendor\\Gone\\RemovedEvent" is unknown');

        new FileEventStore($this->filePath);
    }

    public function test_loading_a_malformed_event_record_fails_loudly(): void
    {
        file_put_contents($this->filePath, json_encode([
            'agg-1' => [
                ['class' => TerminalRegistered::class], // no 'data' key
            ],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the record structure is malformed');

        new FileEventStore($this->filePath);
    }

    public function test_an_unwritable_store_location_fails_loudly(): void
    {
        $this->skipIfRunningAsRoot();

        $dir = $this->filePath . '-dir';
        mkdir($dir, 0o500);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('cannot open lock file');

            // Construction already takes the shared read lock, so an
            // unwritable location fails loudly before any command runs.
            new FileEventStore($dir . '/events.json');
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

    public function test_a_failed_pending_event_cannot_reappear_after_clear(): void
    {
        $this->skipIfRunningAsRoot();

        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1'));

        // Make the save fail so 'agg-2' stays queued in $unpersisted.
        chmod($this->filePath, 0o000);
        try {
            $store->append($this->terminalRegistered('agg-2'));
            $this->fail('Expected the append over an unreadable store to throw');
        } catch (\RuntimeException) {
        } finally {
            chmod($this->filePath, 0o600);
        }

        $store->clear();
        // The next append must not merge the cleared pending 'agg-2' back in.
        $store->append($this->terminalRegistered('agg-3'));

        $reloaded = new FileEventStore($this->filePath);
        $this->assertFalse($reloaded->hasEvents('agg-1'));
        $this->assertFalse($reloaded->hasEvents('agg-2'));
        $this->assertTrue($reloaded->hasEvents('agg-3'));
    }

    public function test_clear_retains_the_shared_lock_sidecar(): void
    {
        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1'));

        $lockPath = $this->filePath . '.lock';
        $this->assertFileExists($lockPath);
        $inodeBeforeClear = fileinode($lockPath);

        $store->clear();

        // Writers coordinate on this one inode; clear() must never replace it.
        clearstatcache();
        $this->assertFileExists($lockPath);
        $this->assertSame($inodeBeforeClear, fileinode($lockPath));

        $store->append($this->terminalRegistered('agg-2'));
        clearstatcache();
        $this->assertSame($inodeBeforeClear, fileinode($lockPath));
    }

    public function test_a_failed_deletion_fails_clear_and_keeps_history_coherent(): void
    {
        $this->skipIfRunningAsRoot();

        $dir = $this->filePath . '-dir';
        mkdir($dir, 0o700);
        $path = $dir . '/events.json';
        $store = new FileEventStore($path);
        $store->append($this->terminalRegistered('agg-1'));

        // Deleting a file needs write permission on its DIRECTORY; locking
        // still works because the existing sidecar only needs to be opened.
        chmod($dir, 0o500);

        try {
            $store->clear();
            $this->fail('Expected a RuntimeException for the failed deletion');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('state NOT cleared', $exception->getMessage());
        } finally {
            chmod($dir, 0o700);
        }

        try {
            // Neither the persisted nor the in-memory history was half-cleared.
            $this->assertTrue($store->hasEvents('agg-1'));
            $this->assertTrue((new FileEventStore($path))->hasEvents('agg-1'));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    public function test_a_reader_waits_out_a_coordinated_reset_window(): void
    {
        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1'));

        // Background process: holds the exclusive sidecar lock while the data
        // file is temporarily moved aside — DemoReset's move-aside window.
        // A reader constructed inside that window must block on the shared
        // lock and then see the restored history, never a silently empty
        // store.
        $script = $this->filePath . '-holder.php';
        file_put_contents($script, <<<'PHP'
            <?php
            $path = $argv[1];
            $handle = fopen($path . '.lock', 'c');
            flock($handle, LOCK_EX);
            rename($path, $path . '.bak');
            touch($path . '.window');
            usleep(1200000);
            rename($path . '.bak', $path);
            flock($handle, LOCK_UN);
            fclose($handle);
            unlink($path . '.window');
            PHP);
        exec(sprintf(
            '%s %s %s > /dev/null 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
            escapeshellarg($this->filePath)
        ));

        try {
            // Wait until the holder has the lock and the file is moved aside.
            $deadline = microtime(true) + 5.0;
            while (!is_file($this->filePath . '.window')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Lock-holder process never signalled the reset window');
                }
                usleep(20000);
            }

            $reader = new FileEventStore($this->filePath);

            $this->assertTrue($reader->hasEvents('agg-1'));
        } finally {
            // Let the holder finish before cleanup so tearDown sees stable files.
            $deadline = microtime(true) + 5.0;
            while (is_file($this->filePath . '.window') && microtime(true) < $deadline) {
                usleep(20000);
            }
            foreach ([$script, $this->filePath . '.bak', $this->filePath . '.window'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
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

    public function test_an_event_claiming_a_taken_version_is_refused(): void
    {
        // The check a handler's expected-version cannot make: each demo
        // process answers version questions from the history it snapshotted
        // at startup, so only the store — inside its write lock, against the
        // current file — can see that another process got there first.
        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1', 1));
        $store->append($this->terminalRegistered('agg-1', 2));

        $rival = new FileEventStore($this->filePath);

        $this->expectException(ConcurrencyException::class);
        $this->expectExceptionMessage('expected version 1, but found version 2');

        $rival->append($this->terminalRegistered('agg-1', 2));
    }

    public function test_a_refused_event_leaves_the_history_untouched(): void
    {
        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1', 1));

        try {
            (new FileEventStore($this->filePath))->append($this->terminalRegistered('agg-1', 1));
        } catch (ConcurrencyException) {
        }

        $this->assertCount(1, (new FileEventStore($this->filePath))->loadEvents('agg-1'));
    }

    public function test_a_multi_event_append_is_all_or_nothing(): void
    {
        // A command recording two events must not leave the first one behind
        // when the second collides.
        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1', 1));

        $rival = new FileEventStore($this->filePath);

        try {
            $rival->appendAll([
                $this->terminalRegistered('agg-1', 2),
                $this->terminalRegistered('agg-1', 2),
            ]);
            $this->fail('Expected the colliding second event to be refused');
        } catch (ConcurrencyException) {
        }

        $this->assertCount(1, (new FileEventStore($this->filePath))->loadEvents('agg-1'));
    }

    public function test_consecutive_events_of_one_command_are_accepted(): void
    {
        $store = new FileEventStore($this->filePath);

        $store->appendAll([
            $this->terminalRegistered('agg-1', 1),
            $this->terminalRegistered('agg-1', 2),
        ]);

        $this->assertCount(2, (new FileEventStore($this->filePath))->loadEvents('agg-1'));
    }

    public function test_a_malformed_history_is_reported_as_such_not_as_a_race(): void
    {
        // Data written before this store checked versions can already hold
        // two events claiming one version. Retrying against it can never
        // succeed, so it must not read like a transient conflict.
        $this->seedMalformedStream();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is malformed');

        // What a real command produces: it replayed 3 rows, the last claiming
        // version 2, so it records version 3 — which the row count says is
        // already taken.
        (new FileEventStore($this->filePath))->append($this->terminalRegistered('agg-1', 3));
    }

    public function test_reading_a_malformed_stream_reports_it_rather_than_returning_it(): void
    {
        $this->seedMalformedStream();
        $store = new FileEventStore($this->filePath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is malformed');

        $store->loadEvents('agg-1');
    }

    public function test_a_malformed_stream_is_still_offered_to_the_projection(): void
    {
        // allEvents() feeds read models that GUARD things — active sessions
        // on a shift, occupancy of a terminal. Hiding rows from a guard makes
        // the guard permissive, which is how a shift once closed with an
        // active session and a busy terminal seeded as free. Callers that
        // depend on those guards refuse while a stream is malformed; this
        // method never makes that decision for them by withholding data.
        $this->seedMalformedStream();
        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-healthy', 1));

        $projected = $store->allEvents();

        $this->assertArrayHasKey('agg-1', $projected);
        $this->assertArrayHasKey('agg-healthy', $projected);
    }

    public function test_malformed_streams_are_reported_for_the_operator(): void
    {
        $this->seedMalformedStream();

        $malformed = (new FileEventStore($this->filePath))->malformedStreams();

        $this->assertArrayHasKey('agg-1', $malformed);
        $this->assertStringContainsString('state clear', $malformed['agg-1']);
    }

    public function test_a_healthy_store_reports_no_malformed_streams(): void
    {
        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1', 1));
        $store->append($this->terminalRegistered('agg-1', 2));

        $this->assertSame([], (new FileEventStore($this->filePath))->malformedStreams());
    }

    /**
     * The artifact an older build could write: two events claiming version 2.
     */
    private function seedMalformedStream(): void
    {
        $store = new FileEventStore($this->filePath);
        $store->append($this->terminalRegistered('agg-1', 1));
        $store->append($this->terminalRegistered('agg-1', 2));

        $onDisk = json_decode((string) file_get_contents($this->filePath), true);
        $onDisk['agg-1'][] = $onDisk['agg-1'][1];
        file_put_contents($this->filePath, json_encode($onDisk));
    }

    private function terminalRegistered(string $aggregateRootUuid, int $version = 1): AggregateEvent
    {
        return TerminalRegistered::occur(
            new TerminalId(),
            new BranchId(),
            'Demo Terminal',
            new DateTimeImmutable()
        )->withMetadata([
            AggregateEvent::META_AGGREGATE_ROOT_UUID    => $aggregateRootUuid,
            AggregateEvent::META_AGGREGATE_ROOT_TYPE    => 'Terminal',
            AggregateEvent::META_AGGREGATE_ROOT_VERSION => $version,
        ]);
    }
}
