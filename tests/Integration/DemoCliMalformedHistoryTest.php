<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * A history holding two events that claim the same version cannot be
 * ordered. An older build could write one, so the demo has to cope with it.
 *
 * The rule these tests pin: commands whose correctness depends on replaying
 * that history REFUSE, and nothing silently answers a guard's question from
 * a history it cannot order. An earlier attempt hid such streams from the
 * projection instead, which quietly turned two strict guards permissive —
 * a shift closed with an active session still open, and an occupied
 * terminal seeding as free.
 */
final class DemoCliMalformedHistoryTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = tempnam(sys_get_temp_dir(), 'pos-demo-malformed-');
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

    public function test_shift_commands_refuse_while_a_history_cannot_be_ordered(): void
    {
        $shiftId = $this->openShift();
        $this->corruptStream($shiftId);

        foreach (['shift close --declared-cash=50000', 'shift open --opening-cash=50000'] as $arguments) {
            [$exitCode, $output] = $this->runDemoCli($arguments);

            $this->assertSame(1, $exitCode, "`{$arguments}` should have refused:\n" . $output);
            $this->assertStringContainsString('cannot be ordered', $output);
            $this->assertStringContainsString('state clear', $output);
        }
    }

    public function test_reconcile_refuses_rather_than_rebuilding_from_it(): void
    {
        $shiftId = $this->openShift();
        $this->corruptStream($shiftId);

        [$exitCode, $output] = $this->runDemoCli('shift reconcile');

        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString('cannot be ordered', $output);
    }

    public function test_the_terminal_of_a_malformed_shift_is_not_quietly_freed(): void
    {
        // Rebuilding the slots from a history the demo refuses to replay is
        // exactly how an occupied terminal once seeded as free. Deleting the
        // slot file is the first-run-after-upgrade case.
        $shiftId = $this->openShift();
        $this->corruptStream($shiftId);
        unlink($this->dataDir . '/shift-slots.json');

        [$exitCode, $output] = $this->runDemoCli('shift open --opening-cash=50000');

        $this->assertSame(1, $exitCode, "The occupied terminal must not be reopened:\n" . $output);
        $this->assertSame(
            [],
            glob($this->dataDir . '/shift-slots.json') ?: [],
            'A history that cannot be ordered must not be re-seeded into slots at all'
        );
    }

    public function test_an_ungated_command_does_not_re_seed_slots_from_an_unorderable_history(): void
    {
        // The seed only runs when the slot file is absent — first run after
        // upgrade, or a copied-in event file — and it runs on EVERY command,
        // including the ungated ones. Deciding what is occupied from a
        // history whose order is ambiguous is the thing to avoid: replayed
        // one way the shift is open, the other way its terminal is free.
        $shiftId = $this->openShift();
        $this->corruptStream($shiftId);
        unlink($this->dataDir . '/shift-slots.json');

        [$exitCode] = $this->runDemoCli('terminal list');

        $this->assertSame(0, $exitCode, 'An unrelated command must still run');
        $this->assertFileDoesNotExist(
            $this->dataDir . '/shift-slots.json',
            'An ungated command must not re-seed slots from an unorderable history'
        );
    }

    public function test_a_corrupt_terminal_history_fails_loudly_and_still_clears(): void
    {
        $this->runDemoCliOrFail('terminal register --name=Corrupt-Terminal');
        $state = json_decode((string) file_get_contents($this->dataDir . '/demo-state.json'), true);
        $this->corruptStream((string) $state['last_terminal_id']);

        // Terminal commands are not gated, so they surface the store's own
        // refusal — loudly, with the remedy, never a quiet wrong answer.
        [$getExit, $getOutput] = $this->runDemoCli('terminal get');
        $this->assertSame(1, $getExit, $getOutput);
        $this->assertStringContainsString('state clear', $getOutput);

        [$clearExit] = $this->runDemoCli('state clear');
        $this->assertSame(0, $clearExit);
    }

    public function test_a_shift_cannot_be_closed_while_a_session_history_cannot_be_ordered(): void
    {
        // The close guard asks the SESSION read model whether any session is
        // still active. Answering that from a history the demo cannot order
        // is how a shift once closed — releasing its slots — with an active
        // session and an open order still on the books.
        $this->openShift();
        $this->runDemoCliOrFail('session start');
        $this->runDemoCliOrFail('session new-order');
        $state = json_decode((string) file_get_contents($this->dataDir . '/demo-state.json'), true);
        $this->corruptStream((string) $state['last_session_id']);

        [$exitCode, $output] = $this->runDemoCli('shift close --declared-cash=50000');

        $this->assertSame(1, $exitCode, "The close must not proceed:\n" . $output);
        $this->assertStringContainsString('cannot be ordered', $output);
    }

    public function test_unrelated_commands_and_the_documented_remedy_still_work(): void
    {
        // One unusable stream must not take the whole CLI down — the way out
        // has to stay reachable.
        $shiftId = $this->openShift();
        $this->corruptStream($shiftId);

        [$listExit] = $this->runDemoCli('terminal list');
        $this->assertSame(0, $listExit);

        [$clearExit, $clearOutput] = $this->runDemoCli('state clear');
        $this->assertSame(0, $clearExit, $clearOutput);

        [$openExit, $openOutput] = $this->runDemoCli('terminal register --name=Fresh');
        $this->assertSame(0, $openExit, $openOutput);
    }

    private function openShift(): string
    {
        $this->runDemoCliOrFail('terminal register --name=Malformed-Terminal');
        $this->runDemoCliOrFail('shift open --opening-cash=50000');

        $state = json_decode((string) file_get_contents($this->dataDir . '/demo-state.json'), true);

        return (string) $state['last_shift_id'];
    }

    /**
     * Duplicate the stream's last event — the artifact two racing commands
     * produced before the event store checked versions.
     */
    private function corruptStream(string $aggregateId): void
    {
        $path   = $this->dataDir . '/events.json';
        $onDisk = json_decode((string) file_get_contents($path), true);
        $rows   = $onDisk[$aggregateId];
        $onDisk[$aggregateId][] = $rows[array_key_last($rows)];
        file_put_contents($path, json_encode($onDisk));
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
