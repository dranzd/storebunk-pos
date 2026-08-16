<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Application\PosSession\ReadModel\PosSessionReadModelInterface;
use Dranzd\StorebunkPos\Application\Shift\Command\CloseShift;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Service\ShiftClosePolicy;

/**
 * CloseShiftHandler
 *
 * Handles the CloseShift command by closing the shift with the declared
 * closing cash amount, after asserting no active POS sessions remain.
 */
final class CloseShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly ShiftClosePolicy $shiftClosePolicy,
        private readonly PosSessionReadModelInterface $posSessionReadModel
    ) {
    }

    public function __invoke(CloseShift $command): void
    {
        $shiftId = ShiftId::fromNative($command->shiftId);

        $activeSessions = $this->posSessionReadModel->findActiveByShiftId(
            $shiftId->toNative()
        );

        $this->shiftClosePolicy->assertCanClose($shiftId, $activeSessions);

        $shift = $this->shiftRepository->load($shiftId);
        $shift->close(Money::fromArray([
            'amount' => $command->declaredClosingCashAmount,
            'currency' => $command->currency,
        ]));
        $this->shiftRepository->store($shift);
    }
}
