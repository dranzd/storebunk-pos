<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession;

use DateTimeImmutable;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRoot;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRootTrait;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\CheckoutInitiated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\NewOrderStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCancelledViaPOS;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCompleted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCreatedOffline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderDeactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderMarkedPendingSync;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderParked;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderReactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderResumed;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderSyncedOnline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\PaymentRequested;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionEnded;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionState;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

final class PosSession implements AggregateRoot
{
    use AggregateRootTrait;

    private SessionId $sessionId;
    private ShiftId $shiftId;
    private TerminalId $terminalId;
    private CashierId $cashierId;
    private SessionState $state;
    private ?OrderId $activeOrderId = null;
    /** @var OrderId[] */
    private array $parkedOrderIds = [];
    /** @var OrderId[] */
    private array $inactiveOrderIds = [];
    /** @var OrderId[] */
    private array $pendingSyncOrderIds = [];

    /**
     * Every order this session has started, including ones long since
     * completed or cancelled, against the command that started it (null for
     * online creation, which carries no command id). An id identifies one
     * order for the life of the session, so it cannot be handed back in a
     * second time — but the SAME command arriving twice is a redelivery, not
     * a reuse, and the command id is what tells them apart.
     *
     * @var array<int, array{order: OrderId, command: string|null}>
     */
    private array $startedOrderIds = [];
    /**
     * Orders already synced online, against the command that synced each one
     * — null for events written before the command id was recorded. Lets a
     * redelivered sync heal instead of tripping the pending-sync invariant,
     * and lets an UNRELATED command naming a synced order be told apart from
     * that redelivery. Grows with session lifetime, like the other lists.
     *
     * @var array<int, array{order: OrderId, command: string|null}>
     */
    private array $syncedOrderIds = [];

    final public static function start(
        SessionId $sessionId,
        ShiftId $shiftId,
        TerminalId $terminalId,
        CashierId $cashierId
    ): self {
        $session = new self();
        $session->sessionId = $sessionId;
        $session->recordThat(
            SessionStarted::occur(
                $sessionId,
                $shiftId,
                $terminalId,
                $cashierId,
                new DateTimeImmutable()
            )
        );

        return $session;
    }

    final public function startNewOrder(OrderId $orderId): void
    {
        if ($this->activeOrderId !== null) {
            throw InvariantViolationException::withMessage(
                'Cannot start new order when an order is already active'
            );
        }

        $this->assertOrderIdIsUnused($orderId);

        $this->recordThat(
            NewOrderStarted::occur(
                $this->sessionId,
                $orderId,
                new DateTimeImmutable()
            )
        );
    }

    final public function parkOrder(): void
    {
        if ($this->activeOrderId === null) {
            throw InvariantViolationException::withMessage('No active order to park');
        }

        if ($this->state->isCheckout() || $this->state->isPayment()) {
            throw InvariantViolationException::withMessage(
                'Cannot park an order during checkout'
            );
        }

        $this->recordThat(
            OrderParked::occur(
                $this->sessionId,
                $this->activeOrderId,
                new DateTimeImmutable()
            )
        );
    }

    final public function resumeOrder(OrderId $orderId): void
    {
        if ($this->activeOrderId !== null) {
            throw InvariantViolationException::withMessage(
                'Cannot resume order when an order is already active'
            );
        }

        $isParked = false;
        foreach ($this->parkedOrderIds as $parkedOrderId) {
            if ($parkedOrderId->sameValueAs($orderId)) {
                $isParked = true;
                break;
            }
        }

        if (!$isParked) {
            throw InvariantViolationException::withMessage('Order is not in parked list');
        }

        $this->recordThat(
            OrderResumed::occur(
                $this->sessionId,
                $orderId,
                new DateTimeImmutable()
            )
        );
    }

    final public function initiateCheckout(): void
    {
        if ($this->activeOrderId === null) {
            throw InvariantViolationException::withMessage('No active order to checkout');
        }

        if (!$this->state->isBuilding()) {
            throw InvariantViolationException::withMessage(
                'Can only initiate checkout from Building state'
            );
        }

        $this->recordThat(
            CheckoutInitiated::occur(
                $this->sessionId,
                $this->activeOrderId,
                new DateTimeImmutable()
            )
        );
    }

    final public function requestPayment(Money $amount, string $paymentMethod): void
    {
        if ($this->activeOrderId === null) {
            throw InvariantViolationException::withMessage('No active order for payment');
        }

        // Payment state stays payable so split/partial payments keep working.
        if (!$this->state->isCheckout() && !$this->state->isPayment()) {
            throw InvariantViolationException::withMessage(
                'Can only request payment in Checkout state'
            );
        }

        $this->recordThat(
            PaymentRequested::occur(
                $this->sessionId,
                $this->activeOrderId,
                $amount,
                $paymentMethod,
                new DateTimeImmutable()
            )
        );
    }

    final public function completeOrder(): void
    {
        if ($this->activeOrderId === null) {
            throw InvariantViolationException::withMessage('No active order to complete');
        }

        if (!$this->state->isCheckout() && !$this->state->isPayment()) {
            throw InvariantViolationException::withMessage(
                'Can only complete order in Checkout state'
            );
        }

        $this->recordThat(
            OrderCompleted::occur(
                $this->sessionId,
                $this->activeOrderId,
                new DateTimeImmutable()
            )
        );
    }

    final public function deactivateOrder(string $reason): void
    {
        if ($this->activeOrderId === null) {
            throw InvariantViolationException::withMessage('No active order to deactivate');
        }

        if ($this->state->isCheckout() || $this->state->isPayment()) {
            throw InvariantViolationException::withMessage(
                'Cannot deactivate an order during checkout'
            );
        }

        $this->recordThat(
            OrderDeactivated::occur(
                $this->sessionId,
                $this->activeOrderId,
                $reason,
                new DateTimeImmutable()
            )
        );
    }

    final public function reactivateOrder(OrderId $orderId): void
    {
        if ($this->activeOrderId !== null) {
            throw InvariantViolationException::withMessage(
                'Cannot reactivate order when an order is already active'
            );
        }

        $isInactive = false;
        foreach ($this->inactiveOrderIds as $inactiveOrderId) {
            if ($inactiveOrderId->sameValueAs($orderId)) {
                $isInactive = true;
                break;
            }
        }

        if (!$isInactive) {
            throw InvariantViolationException::withMessage('Order is not in inactive list');
        }

        $this->recordThat(
            OrderReactivated::occur(
                $this->sessionId,
                $orderId,
                new DateTimeImmutable()
            )
        );
    }

    final public function startNewOrderOffline(OrderId $orderId, string $commandId): void
    {
        if ($this->activeOrderId !== null) {
            throw InvariantViolationException::withMessage(
                'Cannot start new order when an order is already active'
            );
        }

        $this->assertOrderIdIsUnused($orderId);

        $this->recordThat(
            OrderCreatedOffline::occur($this->sessionId, $orderId, $commandId)
        );
    }

    final public function markOrderPendingSync(OrderId $orderId): void
    {
        $isActive = $this->activeOrderId !== null && $this->activeOrderId->sameValueAs($orderId);

        if (!$isActive) {
            throw InvariantViolationException::withMessage(
                'Can only mark the active order as pending sync'
            );
        }

        // Pending sync means "created offline, still to be pushed". An order
        // created online was never offline, so queueing it would say nothing
        // true — and a host replaying that history could never rebuild the
        // queue entry's command id, because there is no offline command.
        if (!$this->wasStartedOffline($orderId)) {
            throw InvariantViolationException::withMessage(
                'Only an order created offline can be marked pending sync'
            );
        }

        $this->recordThat(
            OrderMarkedPendingSync::occur($this->sessionId, $orderId)
        );
    }

    /**
     * Whether this order has already been synced online. Lets a redelivered
     * sync command (deterministic message uuid, e.g. after a process restart
     * rebuilt the idempotency registry) be treated as a no-op instead of
     * tripping the pending-sync invariant.
     */
    final public function isOrderSynced(OrderId $orderId): bool
    {
        foreach ($this->syncedOrderIds as $synced) {
            if ($synced['order']->sameValueAs($orderId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Was this order created offline? True only when a command id was
     * recorded for it, which online creation never does.
     */
    private function wasStartedOffline(OrderId $orderId): bool
    {
        foreach ($this->startedOrderIds as $started) {
            if ($started['order']->sameValueAs($orderId)) {
                return $started['command'] !== null;
            }
        }

        return false;
    }

    /**
     * Did THIS command sync this order? True means a redelivery of the
     * syncing command — the only thing that should be absorbed. False for an
     * unrelated command naming an order that merely happens to be synced.
     *
     * Events written before the command id was recorded answer true for any
     * command: they cannot tell, and refusing them would break history that
     * was legitimately synced.
     */
    final public function wasSyncedByCommand(OrderId $orderId, string $commandId): bool
    {
        foreach ($this->syncedOrderIds as $synced) {
            if ($synced['order']->sameValueAs($orderId)) {
                return $synced['command'] === null || $synced['command'] === $commandId;
            }
        }

        return false;
    }

    final public function syncOrderOnline(OrderId $orderId, string $commandId): void
    {
        $isPending = false;
        foreach ($this->pendingSyncOrderIds as $pendingId) {
            if ($pendingId->sameValueAs($orderId)) {
                $isPending = true;
                break;
            }
        }

        if (!$isPending) {
            throw InvariantViolationException::withMessage(
                'Order is not in pending sync list'
            );
        }

        $this->recordThat(
            OrderSyncedOnline::occur($this->sessionId, $orderId, $commandId)
        );
    }

    final public function cancelOrder(string $reason): void
    {
        if ($this->activeOrderId === null) {
            throw InvariantViolationException::withMessage('No active order to cancel');
        }

        if ($this->state->isPayment()) {
            throw InvariantViolationException::withMessage(
                'Cannot cancel an order after payment has been received; complete it instead'
            );
        }

        $this->recordThat(
            OrderCancelledViaPOS::occur(
                $this->sessionId,
                $this->activeOrderId,
                $reason,
                new DateTimeImmutable()
            )
        );
    }

    final public function end(): void
    {
        if ($this->activeOrderId !== null) {
            throw InvariantViolationException::withMessage(
                'Cannot end session with an active order'
            );
        }

        $this->recordThat(
            SessionEnded::occur(
                $this->sessionId,
                new DateTimeImmutable()
            )
        );
    }

    /**
     * The terminal this session runs on. Fixed when the session starts —
     * nothing moves a session between terminals, which is the leg the
     * "an order is only handled from its own terminal" guarantee stands on.
     */
    final public function terminalId(): TerminalId
    {
        return $this->terminalId;
    }

    final public function activeOrderId(): ?OrderId
    {
        return $this->activeOrderId;
    }

    final public function cashierId(): CashierId
    {
        return $this->cashierId;
    }

    final public function getAggregateRootUuid(): string
    {
        return $this->sessionId->toNative();
    }

    private function applyOnSessionStarted(SessionStarted $event): void
    {
        $this->sessionId = $event->getSessionId();
        $this->shiftId = $event->getShiftId();
        $this->terminalId = $event->getTerminalId();
        $this->cashierId = $event->getCashierId();
        $this->state = SessionState::Idle;
    }

    /**
     * Has this session already started this order? Says nothing about WHICH
     * command did — see wasStartedByCommand() for the distinction between a
     * redelivery and an id being reused.
     */
    final public function hasStartedOrder(OrderId $orderId): bool
    {
        foreach ($this->startedOrderIds as $started) {
            if ($started['order']->sameValueAs($orderId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Was this exact order started by this exact command? True means the
     * command has been delivered before — a redelivery to absorb. An order
     * id that matches while the command id does not is a different command
     * reusing an id, which is refused.
     */
    final public function wasStartedByCommand(OrderId $orderId, string $commandId): bool
    {
        foreach ($this->startedOrderIds as $started) {
            if ($started['order']->sameValueAs($orderId)) {
                return $started['command'] === $commandId;
            }
        }

        return false;
    }

    /**
     * The order id arrives from the caller, so it is the one thing about a
     * new order this session cannot take on trust. Reusing an id it already
     * started would put two different orders behind one identifier — the
     * parked one would be reachable through the new one's state.
     *
     * Scoped to THIS session. Two sessions being handed the same id is a host
     * concern: order ids are the Ordering context's to hand out, and a host
     * that lets a caller supply one should check it belongs to the caller's
     * terminal (see MultiTerminalEnforcementService).
     */
    private function assertOrderIdIsUnused(OrderId $orderId): void
    {
        if ($this->hasStartedOrder($orderId)) {
            throw InvariantViolationException::withMessage(
                'Order id has already been used in this session'
            );
        }
    }

    private function applyOnNewOrderStarted(NewOrderStarted $event): void
    {
        $this->activeOrderId = $event->getOrderId();
        $this->startedOrderIds[] = ['order' => $event->getOrderId(), 'command' => null];
        $this->state = SessionState::Building;
    }

    private function applyOnOrderParked(OrderParked $event): void
    {
        $this->parkedOrderIds[] = $event->getOrderId();
        $this->activeOrderId = null;
        $this->state = SessionState::Idle;
    }

    private function applyOnOrderResumed(OrderResumed $event): void
    {
        $this->activeOrderId = $event->getOrderId();
        $this->state = SessionState::Building;

        $this->parkedOrderIds = array_filter(
            $this->parkedOrderIds,
            fn(OrderId $id) => !$id->sameValueAs($event->getOrderId())
        );
    }

    private function applyOnSessionEnded(SessionEnded $event): void
    {
        $this->state = SessionState::Idle;
        $this->activeOrderId = null;
    }

    private function applyOnCheckoutInitiated(CheckoutInitiated $event): void
    {
        $this->state = SessionState::Checkout;
    }

    private function applyOnPaymentRequested(PaymentRequested $event): void
    {
        // Money has been received: from here the order can only be completed
        // (or receive further split payments) — never cancelled, parked, or
        // deactivated by POS. Post-payment cancellation is a manual
        // sales-order operation downstream.
        $this->state = SessionState::Payment;
    }

    private function applyOnOrderCompleted(OrderCompleted $event): void
    {
        $this->activeOrderId = null;
        $this->state = SessionState::Idle;
    }

    private function applyOnOrderCancelledViaPOS(OrderCancelledViaPOS $event): void
    {
        $this->activeOrderId = null;
        $this->state = SessionState::Idle;
    }

    private function applyOnOrderDeactivated(OrderDeactivated $event): void
    {
        $this->inactiveOrderIds[] = $event->getOrderId();
        $this->activeOrderId = null;
        $this->state = SessionState::Idle;
    }

    private function applyOnOrderReactivated(OrderReactivated $event): void
    {
        $this->activeOrderId = $event->getOrderId();
        $this->state = SessionState::Building;

        $this->inactiveOrderIds = array_filter(
            $this->inactiveOrderIds,
            fn(OrderId $id) => !$id->sameValueAs($event->getOrderId())
        );
    }

    private function applyOnOrderCreatedOffline(OrderCreatedOffline $event): void
    {
        $this->activeOrderId = $event->getOrderId();
        $this->startedOrderIds[] = ['order' => $event->getOrderId(), 'command' => $event->getCommandId()];
        $this->state = SessionState::Building;
    }

    private function applyOnOrderMarkedPendingSync(OrderMarkedPendingSync $event): void
    {
        $this->pendingSyncOrderIds[] = $event->getOrderId();
        $this->activeOrderId = null;
        $this->state = SessionState::Idle;
    }

    private function applyOnOrderSyncedOnline(OrderSyncedOnline $event): void
    {
        $this->pendingSyncOrderIds = array_filter(
            $this->pendingSyncOrderIds,
            fn(OrderId $id) => !$id->sameValueAs($event->getOrderId())
        );
        $this->syncedOrderIds[] = ['order' => $event->getOrderId(), 'command' => $event->getCommandId()];
    }
}
