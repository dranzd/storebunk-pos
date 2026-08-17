<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRoot;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRootTrait;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\CashDropRecorded;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftAssigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftForceClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftOpened;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftUnassigned;
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

    private const MAX_FALLBACK_CASHIERS = 3;

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
    private ?CashierId $assignee = null;
    /** @var CashierId[] */
    private array $fallbackCashiers = [];

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

    /**
     * Set the shift's operating membership: an assignee cashier plus an optional
     * set of fallback cashiers (≤3). Re-issuing replaces the membership without
     * re-opening the shift. A shift that was never assigned is "open".
     *
     * @param CashierId[] $fallbackCashiers
     */
    final public function assign(CashierId $assignee, array $fallbackCashiers): void
    {
        if (!$this->status->isOpen()) {
            throw InvariantViolationException::withMessage('Cannot assign a shift that is not open');
        }

        if (count($fallbackCashiers) > self::MAX_FALLBACK_CASHIERS) {
            throw InvariantViolationException::withMessage(
                sprintf('A shift may have at most %d fallback cashiers', self::MAX_FALLBACK_CASHIERS)
            );
        }

        $seen = [];
        foreach ($fallbackCashiers as $fallback) {
            $native = $fallback->toNative();
            if ($native === $assignee->toNative()) {
                throw InvariantViolationException::withMessage('Assignee cannot also be a fallback cashier');
            }
            if (isset($seen[$native])) {
                throw InvariantViolationException::withMessage('Fallback cashiers must be distinct');
            }
            $seen[$native] = true;
        }

        $this->recordThat(
            ShiftAssigned::occur(
                $this->shiftId,
                $assignee,
                array_values($fallbackCashiers),
                new DateTimeImmutable()
            )
        );
    }

    /**
     * Clear the shift's membership, returning it to "open" (no assignee, no
     * fallbacks). The inverse of {@see assign()}.
     */
    final public function unassign(): void
    {
        if (!$this->status->isOpen()) {
            throw InvariantViolationException::withMessage('Cannot unassign a shift that is not open');
        }

        if (!$this->isAssigned()) {
            throw InvariantViolationException::withMessage('Shift is not assigned');
        }

        $this->recordThat(
            ShiftUnassigned::occur(
                $this->shiftId,
                new DateTimeImmutable()
            )
        );
    }

    final public function getAggregateRootUuid(): string
    {
        return $this->shiftId->toNative();
    }

    /**
     * The cashier who opened the shift — the operator a later unassign()
     * hands the shift back to.
     */
    final public function openedBy(): CashierId
    {
        return $this->cashierId;
    }

    /**
     * The shift's assignee, or null when the shift is open (no membership set).
     */
    final public function assignee(): ?CashierId
    {
        return $this->assignee;
    }

    /**
     * @return CashierId[]
     */
    final public function fallbackCashiers(): array
    {
        return $this->fallbackCashiers;
    }

    final public function isAssigned(): bool
    {
        return $this->assignee !== null;
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
        $this->shiftId = $event->getShiftId();
        $this->terminalId = $event->getTerminalId();
        $this->branchId = $event->getBranchId();
        $this->cashierId = $event->getCashierId();
        $this->openingCashAmount = $event->getOpeningCashAmount();
        $this->status = ShiftStatus::Open;
        $this->openedAt = $event->getOpenedAt();
    }

    private function applyOnShiftClosed(ShiftClosed $event): void
    {
        $this->status = ShiftStatus::Closed;
        $this->declaredClosingCashAmount = $event->getDeclaredClosingCashAmount();
        $this->closedAt = $event->getClosedAt();
    }

    private function applyOnShiftForceClosed(ShiftForceClosed $event): void
    {
        $this->status = ShiftStatus::ForceClosed;
        $this->closedAt = $event->getForceClosedAt();
    }

    private function applyOnCashDropRecorded(CashDropRecorded $event): void
    {
        $this->cashDrops[] = CashDrop::record($event->getAmount(), $event->getRecordedAt());
    }

    private function applyOnShiftAssigned(ShiftAssigned $event): void
    {
        $this->assignee = $event->getAssignee();
        $this->fallbackCashiers = $event->getFallbackCashiers();
    }

    private function applyOnShiftUnassigned(ShiftUnassigned $event): void
    {
        $this->assignee = null;
        $this->fallbackCashiers = [];
    }
}
