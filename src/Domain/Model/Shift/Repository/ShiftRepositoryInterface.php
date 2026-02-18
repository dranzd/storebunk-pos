<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Repository;

use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

interface ShiftRepositoryInterface
{
    public function store(Shift $shift): void;

    public function load(ShiftId $shiftId): Shift;
}
