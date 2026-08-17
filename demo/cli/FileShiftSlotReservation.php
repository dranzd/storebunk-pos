<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\MultiTerminalEnforcementService;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotReservationInterface;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

/**
 * Cross-process implementation of the shift slot reservation for the demo
 * CLI: every mutation re-reads the CURRENT slot file and writes it back
 * under the sidecar lock, so two concurrent demo processes serialise on the
 * check-and-claim — the atomicity the read model alone cannot give
 * (reported issue 8003). Occupancy RULES stay in
 * MultiTerminalEnforcementService; this class adds state and atomicity.
 */
final class FileShiftSlotReservation implements ShiftSlotReservationInterface
{
    public function __construct(
        private readonly string $filePath,
        private readonly MultiTerminalEnforcementService $multiTerminalEnforcement = new MultiTerminalEnforcementService()
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

    final public function reserveForOpen(string $terminalId, string $cashierId, string $shiftId): void
    {
        $this->mutate(function (array $slots) use ($terminalId, $cashierId, $shiftId): array {
            if (
                in_array($shiftId, $slots['terminals'], true)
                || in_array($shiftId, $slots['cashiers'], true)
            ) {
                throw InvariantViolationException::withMessage(
                    sprintf('Shift "%s" already holds open-shift slots', $shiftId)
                );
            }
            $this->multiTerminalEnforcement->assertTerminalHasNoOpenShift(
                TerminalId::fromNative($terminalId),
                $slots['terminals']
            );
            $this->multiTerminalEnforcement->assertCashierHasNoOpenShift(
                $cashierId,
                $slots['cashiers']
            );

            $slots['terminals'][$terminalId] = $shiftId;
            $slots['cashiers'][$cashierId]   = $shiftId;

            return $slots;
        });
    }

    final public function transferCashier(string $shiftId, string $newCashierId): ?string
    {
        $previousHolder = null;
        $this->mutate(function (array $slots) use ($shiftId, $newCashierId, &$previousHolder): array {
            if (!in_array($shiftId, $slots['cashiers'], true)) {
                throw InvariantViolationException::withMessage(
                    sprintf('Shift "%s" holds no cashier slot; it is not open here', $shiftId)
                );
            }
            $this->multiTerminalEnforcement->assertCashierFreeForShift(
                $newCashierId,
                $shiftId,
                $slots['cashiers']
            );

            foreach ($slots['cashiers'] as $cashierId => $heldShiftId) {
                if ($heldShiftId === $shiftId && $cashierId !== $newCashierId) {
                    $previousHolder = $cashierId;
                    unset($slots['cashiers'][$cashierId]);
                }
            }
            $slots['cashiers'][$newCashierId] = $shiftId;

            return $slots;
        });

        return $previousHolder;
    }

    final public function compensateTransfer(string $shiftId, string $backToCashierId, string $ifHeldBy): void
    {
        $this->mutate(static function (array $slots) use ($shiftId, $backToCashierId, $ifHeldBy): array {
            // Undo only OUR transfer: a newer command's committed state wins.
            if (($slots['cashiers'][$ifHeldBy] ?? null) !== $shiftId) {
                return $slots;
            }
            unset($slots['cashiers'][$ifHeldBy]);

            $backToCurrent = $slots['cashiers'][$backToCashierId] ?? null;
            if ($backToCurrent === null || $backToCurrent === $shiftId) {
                $slots['cashiers'][$backToCashierId] = $shiftId;
            }

            return $slots;
        });
    }

    final public function releaseShift(string $shiftId): void
    {
        $this->mutate(static function (array $slots) use ($shiftId): array {
            $slots['terminals'] = array_filter(
                $slots['terminals'],
                static fn(string $heldShiftId): bool => $heldShiftId !== $shiftId
            );
            $slots['cashiers'] = array_filter(
                $slots['cashiers'],
                static fn(string $heldShiftId): bool => $heldShiftId !== $shiftId
            );

            return $slots;
        });
    }

    /**
     * Rebuild the slot file from the currently open shifts when it does not
     * exist yet (first run after upgrade, or a fresh scratch directory whose
     * event file was copied in). Existing files are left untouched — they
     * are the live authority.
     *
     * @param array<int, array<string, mixed>> $openShifts rows from the shift read model
     */
    final public function seedIfMissing(array $openShifts): void
    {
        $lockHandle = $this->acquireLock();

        try {
            if (is_file($this->filePath)) {
                return;
            }

            $slots = ['terminals' => [], 'cashiers' => []];
            foreach ($openShifts as $shift) {
                $slots['terminals'][(string) $shift['terminal_id']] = (string) $shift['shift_id'];
                $slots['cashiers'][(string) $shift['cashier_id']]   = (string) $shift['shift_id'];
            }
            $this->persist($slots);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * @param callable(array{terminals: array<string, string>, cashiers: array<string, string>}): array{terminals: array<string, string>, cashiers: array<string, string>} $mutation
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
     * @return array{terminals: array<string, string>, cashiers: array<string, string>}
     */
    private function readFromDisk(): array
    {
        if (!is_file($this->filePath)) {
            return ['terminals' => [], 'cashiers' => []];
        }

        $raw = @file_get_contents($this->filePath);
        if ($raw === false) {
            throw new \RuntimeException(sprintf(
                'Demo shift-slot file %s exists but could not be read.',
                $this->filePath
            ));
        }
        if (trim($raw) === '') {
            return ['terminals' => [], 'cashiers' => []];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !is_array($decoded['terminals'] ?? null) || !is_array($decoded['cashiers'] ?? null)) {
            throw new \RuntimeException(sprintf(
                'Demo shift-slot file %s is not valid JSON. ' .
                'Run ./demo/demo state clear and retry.',
                $this->filePath
            ));
        }

        return ['terminals' => $decoded['terminals'], 'cashiers' => $decoded['cashiers']];
    }

    /**
     * @param array{terminals: array<string, string>, cashiers: array<string, string>} $slots
     */
    private function persist(array $slots): void
    {
        $encoded = json_encode($slots, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
