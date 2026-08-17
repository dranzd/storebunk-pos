<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Demo;

use Dranzd\StorebunkPos\Demo\Cli\DemoReset;
use PHPUnit\Framework\TestCase;

final class DemoResetTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = tempnam(sys_get_temp_dir(), 'pos-demo-reset-');
        unlink($this->dir);
        mkdir($this->dir, 0o700);
        mkdir($this->dir . '/slots', 0o700);
    }

    protected function tearDown(): void
    {
        @chmod($this->dir . '/slots', 0o700);
        array_map('unlink', glob($this->dir . '/slots/*') ?: []);
        rmdir($this->dir . '/slots');
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function test_clear_resets_events_state_and_slots_together(): void
    {
        [$events, $state, $slots] = $this->seedAllThree();

        DemoReset::clearAll($events, $state, $slots);

        $this->assertFileDoesNotExist($events);
        $this->assertFileDoesNotExist($slots);
        $this->assertSame('[]', file_get_contents($state));
        $this->assertFileDoesNotExist($events . '.bak');
        $this->assertFileDoesNotExist($slots . '.bak');
    }

    public function test_a_failed_slot_clear_restores_event_history_and_leaves_state(): void
    {
        $this->skipIfRunningAsRoot();

        [$events, $state, $slots] = $this->seedAllThree();

        // The slot file lives in its own directory so ONLY its move-aside
        // fails; events and state must be rolled back untouched.
        chmod($this->dir . '/slots', 0o500);

        try {
            DemoReset::clearAll($events, $state, $slots);
            $this->fail('Expected the slot move-aside to fail');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('nothing was cleared', $exception->getMessage());
        } finally {
            chmod($this->dir . '/slots', 0o700);
        }

        $this->assertSame('{"events": true}', file_get_contents($events));
        $this->assertSame('{"state": true}', file_get_contents($state));
        $this->assertSame('{"terminals": {}, "cashiers": {}}', file_get_contents($slots));
    }

    /**
     * @return array{string, string, string}
     */
    private function seedAllThree(): array
    {
        $events = $this->dir . '/events.json';
        $state  = $this->dir . '/demo-state.json';
        $slots  = $this->dir . '/slots/shift-slots.json';
        file_put_contents($events, '{"events": true}');
        file_put_contents($state, '{"state": true}');
        file_put_contents($slots, '{"terminals": {}, "cashiers": {}}');
        // Pre-create the slot lock sidecar: the failure-injection test makes
        // the slots DIRECTORY read-only, and the lock must still be openable
        // there so the reset reaches the move-aside step it is testing.
        touch($slots . '.lock');

        return [$events, $state, $slots];
    }

    private function skipIfRunningAsRoot(): void
    {
        // Root ignores permission bits, so the failure injection cannot work.
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('Permission-based failure injection does not work as root.');
        }
    }
}
