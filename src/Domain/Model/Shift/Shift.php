<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRoot;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRootTrait;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\CashDropRecorded;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftForceClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftOpened;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashDrop;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftStatus;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

final class Shift implements AggregateRoot
{
    use AggregateRootTrait;

    private ShiftId $shiftId;
    private TerminalId $terminalId;
    private BranchId $branchId;
    private CashierId $cashierId;
    private ShiftStatus $status;
    private DateTimeImmutable $openedAt;
    private ?DateTimeImmutable $closedAt = null;
    private Money $openingCashAmount;
    private ?Money $declaredClosingCashAmount = null;
    /** @var CashDrop[] */
    private array $cashDrops = [];

    final public static function open(
        ShiftId $shiftId,
        TerminalId $terminalId,
        BranchId $branchId,
        CashierId $cashierId,
        Money $openingCashAmount
    ): self {
        $shift = new self();
        $shift->shiftId = $shiftId;
        $shift->recordThat(
            ShiftOpened::occur(
                $shiftId,
                $terminalId,
                $branchId,
                $cashierId,
                $openingCashAmount,
                new DateTimeImmutable()
            )
        );

        return $shift;
    }

    final public function close(Money $declaredClosingCashAmount): void
    {
        if (!$this->status->isOpen()) {
            throw InvariantViolationException::withMessage('Cannot close a shift that is not open');
        }

        $expectedCash = $this->calculateExpectedCash();
        $variance = $this->calculateVariance($declaredClosingCashAmount, $expectedCash);

        $this->recordThat(
            ShiftClosed::occur(
                $this->shiftId,
                $declaredClosingCashAmount,
                $expectedCash,
                $variance,
                new DateTimeImmutable()
            )
        );
    }

    final public function forceClose(string $supervisorId, string $reason): void
    {
        if (!$this->status->isOpen()) {
            throw InvariantViolationException::withMessage('Cannot force close a shift that is not open');
        }

        $this->recordThat(
            ShiftForceClosed::occur(
                $this->shiftId,
                $supervisorId,
                $reason,
                new DateTimeImmutable()
            )
        );
    }

    final public function recordCashDrop(Money $amount): void
    {
        if (!$this->status->isOpen()) {
            throw InvariantViolationException::withMessage('Cannot record cash drop on a closed shift');
        }

        $this->recordThat(
            CashDropRecorded::occur(
                $this->shiftId,
                $amount,
                new DateTimeImmutable()
            )
        );
    }

    final public function getAggregateRootUuid(): string
    {
        return $this->shiftId->toNative();
    }

    private function calculateExpectedCash(): Money
    {
        $openingArray = $this->openingCashAmount->toArray();
        $expectedAmount = $openingArray['amount'];
        $currency = $openingArray['currency'];

        foreach ($this->cashDrops as $cashDrop) {
            $dropArray = $cashDrop->amount()->toArray();
            $expectedAmount -= $dropArray['amount'];
        }

        return Money::fromArray(['amount' => $expectedAmount, 'currency' => $currency]);
    }

    private function calculateVariance(Money $declared, Money $expected): Money
    {
        $declaredArray = $declared->toArray();
        $expectedArray = $expected->toArray();

        $varianceAmount = $declaredArray['amount'] - $expectedArray['amount'];

        return Money::fromArray(['amount' => $varianceAmount, 'currency' => $declaredArray['currency']]);
    }

    private function applyOnShiftOpened(ShiftOpened $event): void
    {
        $this->shiftId = $event->shiftId();
        $this->terminalId = $event->terminalId();
        $this->branchId = $event->branchId();
        $this->cashierId = $event->cashierId();
        $this->openingCashAmount = $event->openingCashAmount();
        $this->status = ShiftStatus::Open;
        $this->openedAt = $event->openedAt();
    }

    private function applyOnShiftClosed(ShiftClosed $event): void
    {
        $this->status = ShiftStatus::Closed;
        $this->declaredClosingCashAmount = $event->declaredClosingCashAmount();
        $this->closedAt = $event->closedAt();
    }

    private function applyOnShiftForceClosed(ShiftForceClosed $event): void
    {
        $this->status = ShiftStatus::ForceClosed;
        $this->closedAt = $event->forceClosedAt();
    }

    private function applyOnCashDropRecorded(CashDropRecorded $event): void
    {
        $this->cashDrops[] = CashDrop::record($event->amount(), $event->recordedAt());
    }
}
