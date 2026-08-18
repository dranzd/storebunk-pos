<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Repository;

use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;
use Dranzd\StorebunkPos\Shared\Exception\ConcurrencyException;

interface ShiftRepositoryInterface
{
    /**
     * Append the shift's recorded events.
     *
     * @param int|null $expectedVersion the aggregate version the caller read
     *                                  before mutating (0 for a shift that
     *                                  must not exist yet). Implementations
     *                                  MUST refuse the append when the stored
     *                                  version differs — it is what stops two
     *                                  commands that read the same shift from
     *                                  both landing.
     *
     * @throws ConcurrencyException when $expectedVersion does not match
     */
    public function store(Shift $shift, ?int $expectedVersion = null): void;

    /**
     * @throws AggregateNotFoundException when no shift exists for the id —
     *         callers rely on this to tell "absent" from "present"
     */
    public function load(ShiftId $shiftId): Shift;
}
