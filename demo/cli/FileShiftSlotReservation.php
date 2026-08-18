<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

use Dranzd\StorebunkPos\Domain\Service\ShiftSlotBook;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;

/**
 * Cross-process implementation of the shift slot reservation for the demo
 * CLI: every mutation re-reads the CURRENT slot file and writes it back
 * under the sidecar lock, so two concurrent demo processes serialise on the
 * check-and-claim — the atomicity the read model alone cannot give
 * (reported issue 8003). The bookkeeping itself lives in ShiftSlotBook;
 * this class adds the persisted state and the atomicity boundary.
 *
 * In-flight (prepared) claims are persisted alongside committed slots, so a
 * demo process killed mid-command leaves a claim behind on purpose: it keeps
 * blocking until "./demo shift reconcile" rebuilds the file from the events.
 *
 * @phpstan-import-type SlotState from ShiftSlotBook
 */
final class FileShiftSlotReservation implements ShiftSlotReservationInterface
{
    public function __construct(
        private readonly string $filePath,
        private readonly ShiftSlotBook $slotBook = new ShiftSlotBook()
    ) {
    }

    public static function defaultPath(): string
    {
        $dir = getenv('POS_DEMO_DATA_DIR') ?: dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/shift-slots.json';
    }

    /**
     * Shape the shift read model's open-shift rows the way the port expects
     * them — the committed authority both seeding and reconciliation compare
     * the slot file against.
     *
     * @param array<int, array<string, mixed>> $openShiftRows
     *
     * @return array<string, array{terminal_id: string, cashier_id: string}>
     */
    public static function openShiftsById(array $openShiftRows): array
    {
        $byId = [];
        foreach ($openShiftRows as $shift) {
            $byId[(string) $shift['shift_id']] = [
                'terminal_id' => (string) $shift['terminal_id'],
                'cashier_id'  => (string) $shift['cashier_id'],
            ];
        }

        return $byId;
    }

    final public function reserveForOpen(string $terminalId, string $cashierId, string $shiftId): void
    {
        $this->mutate(
            fn (array $slots): array => $this->slotBook->reserveForOpen($slots, $terminalId, $cashierId, $shiftId)
        );
    }

    final public function prepareTransfer(string $shiftId, string $newCashierId): void
    {
        $this->mutate(
            fn (array $slots): array => $this->slotBook->prepareTransfer($slots, $shiftId, $newCashierId)
        );
    }

    final public function commitTransfer(string $shiftId, string $newCashierId): void
    {
        $this->mutate(
            fn (array $slots): array => $this->slotBook->commitTransfer($slots, $shiftId, $newCashierId)
        );
    }

    final public function abortTransfer(string $shiftId, string $newCashierId): void
    {
        $this->mutate(
            fn (array $slots): array => $this->slotBook->abortTransfer($slots, $shiftId, $newCashierId)
        );
    }

    final public function releaseShift(string $shiftId): void
    {
        $this->mutate(
            fn (array $slots): array => $this->slotBook->releaseShift($slots, $shiftId)
        );
    }

    final public function reconcile(array $openShiftsById): int
    {
        $corrections = 0;
        $this->mutate(function (array $slots) use ($openShiftsById, &$corrections): array {
            $reconciled  = $this->slotBook->stateFor($openShiftsById);
            $corrections = $this->slotBook->correctionCount($slots, $reconciled);

            return $reconciled;
        });

        return $corrections;
    }

    /**
     * Rebuild the slot file from the currently open shifts when it does not
     * exist yet (first run after upgrade, or a fresh scratch directory whose
     * event file was copied in). Existing files are left untouched — they
     * are the live authority, and reconciling them here would discard the
     * in-flight claims of a concurrently running demo process.
     *
     * @param array<string, array{terminal_id: string, cashier_id: string}> $openShiftsById
     */
    final public function seedIfMissing(array $openShiftsById): void
    {
        $lockHandle = $this->acquireLock();

        try {
            if (is_file($this->filePath)) {
                return;
            }

            $this->persist($this->slotBook->stateFor($openShiftsById));
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * @param callable(SlotState): SlotState $mutation
     */
    private function mutate(callable $mutation): void
    {
        $lockHandle = $this->acquireLock();

        try {
            $this->persist($mutation($this->readFromDisk()));
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * @return SlotState
     */
    private function readFromDisk(): array
    {
        if (!is_file($this->filePath)) {
            return $this->slotBook->emptyState();
        }

        $raw = @file_get_contents($this->filePath);
        if ($raw === false) {
            throw new \RuntimeException(sprintf(
                'Demo shift-slot file %s exists but could not be read.',
                $this->filePath
            ));
        }
        if (trim($raw) === '') {
            return $this->slotBook->emptyState();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !is_array($decoded['terminals'] ?? null) || !is_array($decoded['cashiers'] ?? null)) {
            throw new \RuntimeException(sprintf(
                'Demo shift-slot file %s is not valid JSON. ' .
                'Run ./demo/demo state clear and retry.',
                $this->filePath
            ));
        }

        return [
            'terminals' => $decoded['terminals'],
            'cashiers'  => $decoded['cashiers'],
            // Files written before the prepare/commit protocol have no
            // in-flight bucket; an absent one simply means "nothing pending".
            'pending'   => is_array($decoded['pending_cashiers'] ?? null) ? $decoded['pending_cashiers'] : [],
        ];
    }

    /**
     * @param SlotState $slots
     */
    private function persist(array $slots): void
    {
        $encoded = json_encode(
            [
                'terminals'        => $slots['terminals'],
                'cashiers'         => $slots['cashiers'],
                'pending_cashiers' => $slots['pending'],
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
        if ($encoded === false) {
            throw new \RuntimeException('Demo shift slots could not be JSON-encoded; slots NOT saved.');
        }

        // Native warnings are suppressed because every failure path throws
        // its own descriptive exception.
        $tmpPath = $this->filePath . '.tmp';
        $written = @file_put_contents($tmpPath, $encoded);
        if ($written !== strlen($encoded)) {
            @unlink($tmpPath);
            throw new \RuntimeException(sprintf(
                'Demo shift-slot file %s could not be written; previous slots left untouched.',
                $this->filePath
            ));
        }
        if (!@rename($tmpPath, $this->filePath)) {
            @unlink($tmpPath);
            throw new \RuntimeException(sprintf(
                'Demo shift-slot file %s could not be replaced; previous slots left untouched.',
                $this->filePath
            ));
        }
    }

    /**
     * The lock lives on a sidecar file whose inode is never replaced or
     * deleted, so every process serialises on the same lock even though the
     * data file is swapped atomically.
     *
     * @return resource
     */
    private function acquireLock()
    {
        $lockHandle = @fopen($this->filePath . '.lock', 'c');
        if ($lockHandle === false) {
            throw new \RuntimeException(sprintf(
                'Demo shift-slot store cannot open lock file %s.lock.',
                $this->filePath
            ));
        }
        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            throw new \RuntimeException(sprintf(
                'Demo shift-slot store cannot lock %s.lock.',
                $this->filePath
            ));
        }

        return $lockHandle;
    }
}
