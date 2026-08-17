<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Stub\Repository;

use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

/**
 * Shift repository whose next store fails — running a callback first, INSIDE
 * the failing store, so a test can drive a controlled interleaving: whatever
 * the callback does happens while the losing command is mid-flight, before
 * its slot claim is rolled back.
 */
final class CallbackFailingShiftRepository implements ShiftRepositoryInterface
{
    public bool $failNextStore = true;

    /** @var callable(): void */
    private $duringFailingStore;

    /**
     * @param callable(): void|null $duringFailingStore
     */
    public function __construct(
        private readonly ShiftRepositoryInterface $inner,
        ?callable $duringFailingStore = null,
        private readonly string $failureMessage = 'store unavailable'
    ) {
        $this->duringFailingStore = $duringFailingStore ?? static function (): void {
        };
    }

    public function load(ShiftId $shiftId): Shift
    {
        return $this->inner->load($shiftId);
    }

    public function store(Shift $shift, ?int $expectedVersion = null): void
    {
        if ($this->failNextStore) {
            $this->failNextStore = false;
            ($this->duringFailingStore)();

            throw new \RuntimeException($this->failureMessage);
        }

        $this->inner->store($shift, $expectedVersion);
    }
}
