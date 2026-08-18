<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Application\Shift\Command\RecordCashDrop;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

/**
 * RecordCashDropHandler
 *
 * Handles the RecordCashDrop command by recording a cash drop on the shift.
 */
final class RecordCashDropHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository
    ) {
    }

    public function __invoke(RecordCashDrop $command): void
    {
        $shift = $this->shiftRepository->load(ShiftId::fromNative($command->shiftId));
        $shift->recordCashDrop(Money::fromArray([
            'amount' => $command->amount,
            'currency' => $command->currency,
        ]));
        // No expected version: a cash drop is additive — nothing it decides
        // depends on what it read, so two drops racing should both land if
        // the store can order them, and be refused by the STORE if it cannot.
        $this->shiftRepository->store($shift);
    }
}
