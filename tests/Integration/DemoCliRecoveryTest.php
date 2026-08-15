<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Integration;

use Dranzd\StorebunkPos\Demo\Cli\FileEventStore;
use Dranzd\StorebunkPos\Demo\Cli\StateStore;
use PHPUnit\Framework\TestCase;

/**
 * ./demo/demo state clear is the documented recovery for corrupt persisted
 * demo data, so it must run even when the stores it resets cannot be loaded.
 * These tests execute the real CLI against a scratch data directory.
 */
final class DemoCliRecoveryTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = tempnam(sys_get_temp_dir(), 'pos-demo-cli-');
        // tempnam() created a file; we need a directory at that path.
        unlink($this->dataDir);
        mkdir($this->dataDir, 0o700);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dataDir . '/*') ?: []);
        if (is_dir($this->dataDir)) {
            rmdir($this->dataDir);
        }
    }

    public function test_state_clear_recovers_from_corrupt_event_store(): void
    {
        file_put_contents($this->dataDir . '/events.json', '{"torn write');

        [$exitCode, $output] = $this->runDemoCli('state clear');

        $this->assertSame(0, $exitCode, "state clear failed:\n" . $output);
        $this->assertStoresAreUsable();
    }

    public function test_state_clear_recovers_from_corrupt_state_file(): void
    {
        file_put_contents($this->dataDir . '/demo-state.json', '{"torn write');

        [$exitCode, $output] = $this->runDemoCli('state clear');

        $this->assertSame(0, $exitCode, "state clear failed:\n" . $output);
        $this->assertStoresAreUsable();
    }

    public function test_state_clear_recovers_when_both_stores_are_corrupt(): void
    {
        file_put_contents($this->dataDir . '/events.json', '{"torn write');
        file_put_contents($this->dataDir . '/demo-state.json', '{"torn write');

        [$exitCode, $output] = $this->runDemoCli('state clear');

        $this->assertSame(0, $exitCode, "state clear failed:\n" . $output);
        $this->assertStoresAreUsable();
    }

    public function test_normal_commands_still_fail_loudly_on_corrupt_stores(): void
    {
        file_put_contents($this->dataDir . '/events.json', '{"torn write');

        [$exitCode, $output] = $this->runDemoCli('terminal list');

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('not valid JSON', $output);
    }

    /**
     * @return array{int, string}
     */
    private function runDemoCli(string $arguments): array
    {
        $command = sprintf(
            'POS_DEMO_DATA_DIR=%s php %s %s 2>&1',
            escapeshellarg($this->dataDir),
            escapeshellarg(dirname(__DIR__, 2) . '/demo/demo'),
            $arguments
        );

        exec($command, $outputLines, $exitCode);

        return [$exitCode, implode("\n", $outputLines)];
    }

    private function assertStoresAreUsable(): void
    {
        $eventStore = new FileEventStore($this->dataDir . '/events.json');
        $stateStore = new StateStore($this->dataDir . '/demo-state.json');

        $this->assertSame([], $eventStore->allEvents());
        $this->assertNull($stateStore->get('session_id'));
    }
}
