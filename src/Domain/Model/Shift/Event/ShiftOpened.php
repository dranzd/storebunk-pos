<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Event;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;

final class ShiftOpened extends BaseAggregateEvent implements DomainEventInterface
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
        $instance = new self();
        $instance->shiftId = $shiftId;
        $instance->terminalId = $terminalId;
        $instance->branchId = $branchId;
        $instance->cashierId = $cashierId;
        $instance->openingCashAmount = $openingCashAmount;
        $instance->openedAt = $openedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return "storebunk.pos.shift.opened";
    }

    final public function getPayload(): array
    {
        return [
            "shift_id" => $this->shiftId->toNative(),
            "terminal_id" => $this->terminalId->toNative(),
            "branch_id" => $this->branchId->toNative(),
            "cashier_id" => $this->cashierId->toNative(),
            "opening_cash_amount" => $this->openingCashAmount->toArray(),
            "opened_at" => $this->openedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->shiftId = ShiftId::fromNative($payload["shift_id"]);
        $this->terminalId = TerminalId::fromNative($payload["terminal_id"]);
        $this->branchId = BranchId::fromNative($payload["branch_id"]);
        $this->cashierId = CashierId::fromNative($payload["cashier_id"]);
        $this->openingCashAmount = Money::fromArray($payload["opening_cash_amount"]);
        $this->openedAt = new DateTimeImmutable($payload["opened_at"]);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    final public function getShiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function getTerminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function getBranchId(): BranchId
    {
        return $this->branchId;
    }

    final public function getCashierId(): CashierId
    {
        return $this->cashierId;
    }

    final public function getOpeningCashAmount(): Money
    {
        return $this->openingCashAmount;
    }

    final public function getOpenedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }
}
