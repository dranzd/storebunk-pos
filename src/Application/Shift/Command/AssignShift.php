<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class AssignShift extends AbstractCommand
{
    /**
     * @param string[] $fallbackCashierIds
     */
    private function __construct(
        private readonly string $shiftId,
        private readonly string $assigneeCashierId,
        private readonly array $fallbackCashierIds,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            [
                'shift_id' => $this->shiftId,
                'assignee_cashier_id' => $this->assigneeCashierId,
                'fallback_cashier_ids' => $this->fallbackCashierIds,
            ]
        );
    }

    /**
     * Assign the shift to an operating cashier, with an optional set of fallback
     * cashiers (≤3) who may operate it when the assignee is out. Re-issuing this
     * replaces the membership without re-opening the shift.
     *
     * @param string[] $fallbackCashierIds
     */
    final public static function toCashier(
        string $shiftId,
        string $assigneeCashierId,
        array $fallbackCashierIds = [],
        ?string $commandId = null
    ): self {
        return new self($shiftId, $assigneeCashierId, array_values($fallbackCashierIds), $commandId ?? '');
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.assign';
    }

    final public function shiftId(): ShiftId
    {
        return ShiftId::fromNative($this->shiftId);
    }

    final public function assignee(): CashierId
    {
        return CashierId::fromNative($this->assigneeCashierId);
    }

    /**
     * @return CashierId[]
     */
    final public function fallbackCashiers(): array
    {
        return array_map(
            static fn (string $id): CashierId => CashierId::fromNative($id),
            $this->fallbackCashierIds
        );
    }
}
