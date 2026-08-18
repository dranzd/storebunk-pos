<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Demo;

use Dranzd\StorebunkPos\Demo\Cli\FileShiftSlotReservation;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class FileShiftSlotReservationTest extends TestCase
{
    private const TERM_1 = '11111111-1111-4111-8111-111111111111';
    private const TERM_2 = '22222222-2222-4222-8222-222222222222';

    private string $dir;
    private string $path;

    protected function setUp(): void
    {
        $this->dir = tempnam(sys_get_temp_dir(), 'pos-slots-');
        unlink($this->dir);
        mkdir($this->dir, 0o700);
        $this->path = $this->dir . '/shift-slots.json';
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function test_seeding_stays_permissive_when_the_history_breaks_the_invariant(): void
    {
        // Seeding runs automatically on EVERY demo command, before the CLI's
        // error handling. If it refused a conflicting history, every command
        // would die at bootstrap — including the close and the reconcile that
        // are the way out. A recovery tool you cannot start is not one.
        $reservation = new FileShiftSlotReservation($this->path);

        $reservation->seedIfMissing([
            'shift-1' => ['terminal_id' => self::TERM_1, 'cashier_id' => 'cash-1'],
            'shift-2' => ['terminal_id' => self::TERM_1, 'cashier_id' => 'cash-2'],
        ]);

        $slots = json_decode((string) file_get_contents($this->path), true);
        $this->assertSame(['shift-2'], array_values($slots['terminals']));
        $this->assertCount(2, $slots['cashiers']);
    }

    public function test_reconcile_refuses_the_same_history_it_seeds(): void
    {
        // The deliberate maintenance command is where the corruption is
        // reported — the operator asked, and can act on the answer.
        $reservation = new FileShiftSlotReservation($this->path);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('held by two open shifts');

        $reservation->reconcile([
            'shift-1' => ['terminal_id' => self::TERM_1, 'cashier_id' => 'cash-1'],
            'shift-2' => ['terminal_id' => self::TERM_1, 'cashier_id' => 'cash-2'],
        ]);
    }

    public function test_seeding_leaves_an_existing_file_alone(): void
    {
        $reservation = new FileShiftSlotReservation($this->path);
        $reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');

        // A concurrent process's live file is the authority — seeding from a
        // replay snapshot must not overwrite it.
        $reservation->seedIfMissing([
            'shift-9' => ['terminal_id' => self::TERM_2, 'cashier_id' => 'cash-9'],
        ]);

        $slots = json_decode((string) file_get_contents($this->path), true);
        $this->assertSame(['shift-1'], array_values($slots['terminals']));
    }

    public function test_a_corrupt_in_flight_list_fails_loudly(): void
    {
        file_put_contents($this->path, json_encode([
            'terminals'        => [],
            'cashiers'         => [],
            'pending_cashiers' => 'garbage',
        ]));
        $reservation = new FileShiftSlotReservation($this->path);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('corrupt in-flight claim list');

        $reservation->reserveForOpen(self::TERM_1, 'cash-1', 'shift-1');
    }

    public function test_a_legacy_file_without_the_in_flight_list_still_loads(): void
    {
        file_put_contents($this->path, json_encode([
            'terminals' => [self::TERM_1 => 'shift-1'],
            'cashiers'  => ['cash-1' => 'shift-1'],
        ]));
        $reservation = new FileShiftSlotReservation($this->path);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');

        $reservation->reserveForOpen(self::TERM_1, 'cash-2', 'shift-2');
    }
}
