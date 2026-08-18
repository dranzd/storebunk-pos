<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Stub\Repository;

use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

/**
 * Runs a callback ONCE, in the middle of a command, so a test can drive a
 * deterministic interleaving: whatever the callback does happens after the
 * command under test has read the shift but before it writes.
 *
 * `$duringFirstStore` fires inside store(), so the rival commits while this
 * command sits between its read and its append — the window optimistic
 * concurrency exists to close. `$duringFirstLoad` fires as soon as this
 * command has read (or failed to find) the shift, which is the earlier
 * window an existence CHECK leaves open.
 */
final class InterleavingShiftRepository implements ShiftRepositoryInterface
{
    private bool $storeFired = false;
    private bool $loadFired = false;

    /** @var callable(): void|null */
    private $duringFirstStore;

    /** @var callable(): void|null */
    private $duringFirstLoad;

    /**
     * @param callable(): void|null $duringFirstStore
     * @param callable(): void|null $duringFirstLoad
     */
    public function __construct(
        private readonly ShiftRepositoryInterface $inner,
        ?callable $duringFirstStore = null,
        ?callable $duringFirstLoad = null
    ) {
        $this->duringFirstStore = $duringFirstStore;
        $this->duringFirstLoad  = $duringFirstLoad;
    }

    public function load(ShiftId $shiftId): Shift
    {
        try {
            return $this->inner->load($shiftId);
        } finally {
            // Fires whether the shift was found or not: an existence check
            // that finds nothing is exactly the case worth interleaving.
            if (!$this->loadFired && $this->duringFirstLoad !== null) {
                $this->loadFired = true;
                ($this->duringFirstLoad)();
            }
        }
    }

    public function store(Shift $shift, ?int $expectedVersion = null): void
    {
        if (!$this->storeFired && $this->duringFirstStore !== null) {
            $this->storeFired = true;
            ($this->duringFirstStore)();
        }

        $this->inner->store($shift, $expectedVersion);
    }
}
