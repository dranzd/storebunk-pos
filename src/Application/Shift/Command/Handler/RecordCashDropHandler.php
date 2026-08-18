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
        // The drop refuses a CLOSED shift, so it does depend on the state it
        // read: without this, a drop that checked "open" could land after a
        // concurrent close, on a shift whose variance was already computed.
        $expectedVersion = $shift->getAggregateRootVersion();
        $shift->recordCashDrop(Money::fromArray([
            'amount' => $command->amount,
            'currency' => $command->currency,
        ]));
        $this->shiftRepository->store($shift, $expectedVersion);
    }
}
