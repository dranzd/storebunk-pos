<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Demo;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateEvent;
use Dranzd\StorebunkPos\Demo\Cli\FileEventStore;
use Dranzd\StorebunkPos\Demo\Cli\StateStore;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use PHPUnit\Framework\TestCase;

final class StateStoreTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        $this->filePath = tempnam(sys_get_temp_dir(), 'pos-demo-state-');
    }

    protected function tearDown(): void
    {
        foreach ([$this->filePath, $this->filePath . '.tmp'] as $path) {
            if (is_file($path)) {
                @chmod($path, 0o600);
                unlink($path);
            }
        }
    }

    public function test_state_survives_a_reload_round_trip(): void
    {
        $store = new StateStore($this->filePath);
        $store->set('session_id', 'session-uuid-1');

        $reloaded = new StateStore($this->filePath);

        $this->assertSame('session-uuid-1', $reloaded->get('session_id'));
    }

    public function test_a_failed_state_write_fails_loudly_and_preserves_state(): void
    {
        $this->skipIfRunningAsRoot();

        [$dir, $path, $store] = $this->storeInGuardedDirectory();
        $store->set('session_id', 'session-uuid-1');
        $persisted = file_get_contents($path);

        // The temp-file write needs directory write permission; revoking it
        // makes persistence fail without touching the existing state file.
        chmod($dir, 0o500);

        try {
            $store->set('order_id', 'order-uuid-1');
            $this->fail('Expected a RuntimeException for the failed state write');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('previous state left untouched', $exception->getMessage());
        } finally {
            chmod($dir, 0o700);
        }

        try {
            // The previous file survives byte-for-byte, and the SAME instance
            // did not keep the unsaved mutation in memory.
            $this->assertSame($persisted, file_get_contents($path));
            $this->assertNull($store->get('order_id'));
            $this->assertSame('session-uuid-1', $store->get('session_id'));
        } finally {
            $this->removeDirectory($dir);
        }
    }

    public function test_a_failed_clear_write_fails_loudly_and_preserves_state(): void
    {
        $this->skipIfRunningAsRoot();

        [$dir, $path, $store] = $this->storeInGuardedDirectory();
        $store->set('session_id', 'session-uuid-1');
        $persisted = file_get_contents($path);

        chmod($dir, 0o500);

        try {
            $store->clear();
            $this->fail('Expected a RuntimeException for the failed state clear');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('previous state left untouched', $exception->getMessage());
        } finally {
            chmod($dir, 0o700);
        }

        try {
            $this->assertSame($persisted, file_get_contents($path));
            $this->assertSame('session-uuid-1', $store->get('session_id'));
        } finally {
            $this->removeDirectory($dir);
        }
    }

    public function test_cli_clear_order_preserves_state_when_event_clear_fails(): void
    {
        $this->skipIfRunningAsRoot();

        // Mirrors ./demo/demo state clear: the fallible event-store clear runs
        // FIRST, so a failure must leave the state/ID references untouched.
        $dir = $this->filePath . '-events-dir';
        mkdir($dir, 0o700);
        $eventStore = new FileEventStore($dir . '/events.json');
        $eventStore->append($this->terminalRegistered('agg-1'));

        $stateStore = new StateStore($this->filePath);
        $stateStore->set('session_id', 'session-uuid-1');

        chmod($dir, 0o500);

        try {
            $eventStore->clear();
            $stateStore->clear();
            $this->fail('Expected a RuntimeException from the event-store clear');
        } catch (\RuntimeException) {
        } finally {
            chmod($dir, 0o700);
        }

        try {
            $this->assertSame('session-uuid-1', (new StateStore($this->filePath))->get('session_id'));
            $this->assertTrue((new FileEventStore($dir . '/events.json'))->hasEvents('agg-1'));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    /**
     * @return array{string, string, StateStore}
     */
    private function storeInGuardedDirectory(): array
    {
        $dir = $this->filePath . '-dir';
        mkdir($dir, 0o700);
        $path = $dir . '/demo-state.json';

        return [$dir, $path, new StateStore($path)];
    }

    private function removeDirectory(string $dir): void
    {
        array_map('unlink', glob($dir . '/*') ?: []);
        rmdir($dir);
    }

    private function skipIfRunningAsRoot(): void
    {
        // Root ignores permission bits, so the failure injection cannot work.
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
