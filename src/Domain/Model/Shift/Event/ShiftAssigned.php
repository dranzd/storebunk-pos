<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Event\BaseAggregateEvent;
use Dranzd\StorebunkPos\Domain\Event\DomainEventInterface;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

/**
 * A shift's operating membership was set: an assignee cashier plus an optional
 * set of fallback cashiers (≤3) who may operate it when the assignee is out.
 *
 * Distinct from the per-session operator (PosSession's `cashier_id`) and from the
 * shift opener (ShiftOpened's `cashier_id`): this is *who is allowed to run* the
 * shift. A shift with no `ShiftAssigned` is "open" — host policy decides who may
 * start a session on it. Emitting this event can change membership without
 * re-opening the shift.
 */
final class ShiftAssigned extends BaseAggregateEvent implements DomainEventInterface
{
    private ShiftId $shiftId;
    private CashierId $assignee;
    /** @var CashierId[] */
    private array $fallbackCashiers;
    private DateTimeImmutable $assignedAt;

    /**
     * @param CashierId[] $fallbackCashiers
     */
    final public static function occur(
        ShiftId $shiftId,
        CashierId $assignee,
        array $fallbackCashiers,
        DateTimeImmutable $assignedAt,
    ): self {
        $instance = new self();
        $instance->shiftId = $shiftId;
        $instance->assignee = $assignee;
        $instance->fallbackCashiers = array_values($fallbackCashiers);
        $instance->assignedAt = $assignedAt;

        return $instance;
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.assigned';
    }

    final public function getPayload(): array
    {
        return [
            'shift_id' => $this->shiftId->toNative(),
            'assignee_cashier_id' => $this->assignee->toNative(),
            'fallback_cashier_ids' => array_map(
                static fn (CashierId $cashier): string => $cashier->toNative(),
                $this->fallbackCashiers
            ),
            'assigned_at' => $this->assignedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    final protected function setPayload(array $payload): void
    {
        if (empty($payload)) {
            return;
        }
        $this->shiftId = ShiftId::fromNative($payload['shift_id']);
        $this->assignee = CashierId::fromNative($payload['assignee_cashier_id']);
        $this->fallbackCashiers = array_map(
            static fn (string $id): CashierId => CashierId::fromNative($id),
            $payload['fallback_cashier_ids']
        );
        $this->assignedAt = new DateTimeImmutable($payload['assigned_at']);
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->assignedAt;
    }

    final public function getShiftId(): ShiftId
    {
        return $this->shiftId;
    }

    final public function getAssignee(): CashierId
    {
        return $this->assignee;
    }

    /**
     * @return CashierId[]
     */
    final public function getFallbackCashiers(): array
    {
        return $this->fallbackCashiers;
    }

    final public function getAssignedAt(): DateTimeImmutable
    {
        return $this->assignedAt;
    }
}
