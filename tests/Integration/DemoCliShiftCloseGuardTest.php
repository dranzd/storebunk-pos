<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * The demo CLI runs each command in its own process, so CloseShiftHandler's
 * active-session guard only works if the session read model is re-projected
 * from persisted events on every bootstrap. Before that projection existed,
 * the guard read an always-empty model and a shift could close over a
 * running session.
 */
final class DemoCliShiftCloseGuardTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = tempnam(sys_get_temp_dir(), 'pos-demo-shift-');
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

    public function test_shift_close_is_blocked_while_a_session_is_active(): void
    {
        $this->runDemoCliOrFail('terminal register --name=POS-Guard');
        $this->runDemoCliOrFail('shift open --opening-cash=25000');
        $this->runDemoCliOrFail('session start');
        $this->runDemoCliOrFail('session new-order');

        [$exitCode, $output] = $this->runDemoCli('shift close --declared-cash=25000');

        $this->assertNotSame(0, $exitCode, "shift close should have been refused:\n" . $output);
        $this->assertStringContainsString('active POS session', $output);

        // Ending the session unblocks the close.
        $this->runDemoCliOrFail('session cancel --reason="wrap up"');
        $this->runDemoCliOrFail('session end');
        $this->runDemoCliOrFail('shift close --declared-cash=25000');
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

    private function runDemoCliOrFail(string $arguments): void
    {
        [$exitCode, $output] = $this->runDemoCli($arguments);

        $this->assertSame(0, $exitCode, "`{$arguments}` failed:\n" . $output);
    }
}
