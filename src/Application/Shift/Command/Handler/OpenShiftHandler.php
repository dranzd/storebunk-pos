<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command\Handler;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Application\Shift\ReadModel\ShiftReadModelInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Repository\ShiftRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\Shift;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\MultiTerminalEnforcementService;

/**
 * OpenShiftHandler
 *
 * Handles the OpenShift command by opening a new Shift aggregate, after
 * asserting the terminal and the cashier have no open shift already.
 */
final class OpenShiftHandler
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly MultiTerminalEnforcementService $multiTerminalEnforcement,
        private readonly ShiftReadModelInterface $shiftReadModel
    ) {
    }

    public function __invoke(OpenShift $command): void
    {
        $terminalId = TerminalId::fromNative($command->terminalId);

        $this->multiTerminalEnforcement->assertTerminalHasNoOpenShift(
            $terminalId,
            $this->shiftReadModel->openShiftsByTerminal()
        );
        $this->multiTerminalEnforcement->assertCashierHasNoOpenShift(
            $command->cashierId,
            $this->shiftReadModel->activeTerminalByCashier()
        );

        $shift = Shift::open(
            ShiftId::fromNative($command->shiftId),
            $terminalId,
            BranchId::fromNative($command->branchId),
            CashierId::fromNative($command->cashierId),
            Money::fromArray([
                'amount' => $command->openingCashAmount,
                'currency' => $command->currency,
            ])
        );

        $this->shiftRepository->store($shift);
    }
}
