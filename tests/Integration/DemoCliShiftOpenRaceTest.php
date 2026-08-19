<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue 8003: the shift-slot reservation must be atomic ACROSS PROCESSES.
 * Two demo CLI invocations racing to open a shift on the same terminal (or
 * for the same cashier) must resolve to exactly one winner — the file-lock
 * backed FileShiftSlotReservation is the serialisation point the projection
 * check alone could not provide.
 */
final class DemoCliShiftOpenRaceTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = tempnam(sys_get_temp_dir(), 'pos-demo-race-');
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

    public function test_exactly_one_of_two_concurrent_opens_wins_the_terminal(): void
    {
        $this->runDemoCliOrFail('terminal register --name=Race-Terminal');

        [$exitA, $exitB, $outputs] = $this->runTwoConcurrently(
            'shift open --opening-cash=10000',
            'shift open --opening-cash=20000'
        );

        $this->assertSame(
            1,
            (int) ($exitA === 0) + (int) ($exitB === 0),
            "Exactly one concurrent open should succeed:\n" . $outputs
        );
        $this->assertStringContainsString('already has an open shift', $outputs);
    }

    public function test_exactly_one_of_two_concurrent_opens_wins_the_cashier(): void
    {
        $this->runDemoCliOrFail('terminal register --name=Race-Term-A');
        $this->runDemoCliOrFail('terminal register --name=Race-Term-B');

        // Two different terminals, same cashier.
        $cashier = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
        $terminals = $this->registeredTerminalIds();
        $this->assertCount(2, $terminals);

        [$exitA, $exitB, $outputs] = $this->runTwoConcurrently(
            sprintf('shift open --opening-cash=10000 --terminal-id=%s --cashier-id=%s', $terminals[0], $cashier),
            sprintf('shift open --opening-cash=20000 --terminal-id=%s --cashier-id=%s', $terminals[1], $cashier)
        );

        $this->assertSame(
            1,
            (int) ($exitA === 0) + (int) ($exitB === 0),
            "Exactly one concurrent open should succeed:\n" . $outputs
        );
        $this->assertStringContainsString('already has an open shift on another terminal', $outputs);
    }

    /**
     * Launch two demo CLI commands at the same time and wait for both.
     *
     * @return array{int, int, string}
     */
    private function runTwoConcurrently(string $argumentsA, string $argumentsB): array
    {
        $processes = [];
        $pipes = [];
        foreach ([$argumentsA, $argumentsB] as $i => $arguments) {
            $command = sprintf(
                'POS_DEMO_DATA_DIR=%s php %s %s 2>&1',
                escapeshellarg($this->dataDir),
                escapeshellarg(dirname(__DIR__, 2) . '/demo/demo'),
                $arguments
            );
            $processes[$i] = proc_open($command, [1 => ['pipe', 'w']], $pipes[$i]);
            $this->assertIsResource($processes[$i]);
        }

        $outputs = [];
        $exitCodes = [];
        foreach ($processes as $i => $process) {
            $outputs[$i] = (string) stream_get_contents($pipes[$i][1]);
            fclose($pipes[$i][1]);
            $exitCodes[$i] = proc_close($process);
        }

        return [$exitCodes[0], $exitCodes[1], implode("\n---\n", $outputs)];
    }

    /**
     * @return list<string>
     */
    private function registeredTerminalIds(): array
    {
        $state = json_decode(
            (string) file_get_contents($this->dataDir . '/demo-state.json'),
            true
        );

        return array_values($state['terminal_ids'] ?? []);
    }

    public function test_two_concurrent_cash_drops_both_survive(): void
    {
        // Cash drops are additive and they are real money: losing the version
        // race must cost a retry, never the record. The CLI re-reads the
        // history and tries again rather than reporting an error the operator
        // cannot act on.
        $this->runDemoCliOrFail('terminal register --name=Drop-Terminal');
        $this->runDemoCliOrFail('shift open --opening-cash=50000');
        $state   = json_decode((string) file_get_contents($this->dataDir . '/demo-state.json'), true);
        $shiftId = (string) $state['last_shift_id'];

        [$exitA, $exitB, $outputs] = $this->runTwoConcurrently(
            "shift cash-drop --shift-id={$shiftId} --amount=1000",
            "shift cash-drop --shift-id={$shiftId} --amount=2000"
        );

        $this->assertSame(0, $exitA, $outputs);
        $this->assertSame(0, $exitB, $outputs);

        $persisted = json_decode((string) file_get_contents($this->dataDir . '/events.json'), true);
        $drops     = array_filter(
            $persisted[$shiftId],
            static fn (array $row): bool => str_contains((string) $row['class'], 'CashDropRecorded')
        );
        $this->assertCount(2, $drops, 'Both cash drops must be persisted');
    }

    public function test_assigning_another_shift_does_not_move_the_session_default(): void
    {
        // `last_cashier_id` is read together with `last_shift_id`, so writing
        // it while assigning a DIFFERENT shift starts the next session on one
        // shift under another shift's cashier. Found by review after the
        // default was made to follow the current operator.
        $this->runDemoCliOrFail('terminal register --name=Default-T1');
        $this->runDemoCliOrFail('shift open --opening-cash=50000');
        $firstShiftId = $this->stateValue('last_shift_id');

        $this->runDemoCliOrFail('terminal register --name=Default-T2');
        $this->runDemoCliOrFail('shift open --opening-cash=50000');
        $secondShiftCashier = $this->stateValue('last_cashier_id');

        $this->runDemoCliOrFail(
            "shift assign --shift-id={$firstShiftId} --assignee-id=11111111-1111-4111-8111-111111111111"
        );

        $this->assertSame(
            $secondShiftCashier,
            $this->stateValue('last_cashier_id'),
            'Assigning another shift must not move the default off this shift'
        );
    }

    private function stateValue(string $key): string
    {
        $state = json_decode((string) file_get_contents($this->dataDir . '/demo-state.json'), true);

        return (string) $state[$key];
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
