<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Event;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class ShiftOpened extends AbstractAggregateEvent implements DomainEventInterface
{
    private ShiftId $shiftId;
    private TerminalId $terminalId;
    private BranchId $branchId;
    private CashierId $cashierId;
    private Money $openingCashAmount;
    private DateTimeImmutable $openedAt;

    final public static function occur(
        ShiftId $shiftId,
        TerminalId $terminalId,
        BranchId $branchId,
        CashierId $cashierId,
        Money $openingCashAmount,
        DateTimeImmutable $openedAt
    ): self {
        $event = new self();
        $event->shiftId = $shiftId;
        $event->terminalId = $terminalId;
        $event->branchId = $branchId;
        $event->cashierId = $cashierId;
        $event->openingCashAmount = $openingCashAmount;
        $event->openedAt = $openedAt;

        return $event;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.opened';
    }

    final public function toArray(): array
    {
        return [
            'shift_id' => $this->shiftId->toNative(),
            'terminal_id' => $this->terminalId->toNative(),
            'branch_id' => $this->branchId->toNative(),
            'cashier_id' => $this->cashierId->toNative(),
            'opening_cash_amount' => $this->openingCashAmount->toArray(),
            'opened_at' => $this->openedAt->format(DATE_ATOM),
        ];
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    final public function shiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function branchId(): BranchId
    {
        return $this->branchId;
    }

    final public function cashierId(): CashierId
    {
        return $this->cashierId;
    }

    final public function openingCashAmount(): Money
    {
        return $this->openingCashAmount;
    }

    final public function openedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }
}
