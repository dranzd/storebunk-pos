<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRoot;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRootTrait;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\NewOrderStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderParked;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderResumed;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionEnded;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;
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
    private SessionState $state;
    private ?OrderId $activeOrderId = null;
    /** @var OrderId[] */
    private array $parkedOrderIds = [];

    final public static function start(
        SessionId $sessionId,
        ShiftId $shiftId,
        TerminalId $terminalId
    ): self {
        $session = new self();
        $session->sessionId = $sessionId;
        $session->recordThat(
            SessionStarted::occur(
                $sessionId,
                $shiftId,
                $terminalId,
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

    final public function getAggregateRootUuid(): string
    {
        return $this->sessionId->toNative();
    }

    private function applyOnSessionStarted(SessionStarted $event): void
    {
        $this->sessionId = $event->sessionId();
        $this->shiftId = $event->shiftId();
        $this->terminalId = $event->terminalId();
        $this->state = SessionState::Idle;
    }

    private function applyOnNewOrderStarted(NewOrderStarted $event): void
    {
        $this->activeOrderId = $event->orderId();
        $this->state = SessionState::Building;
    }

    private function applyOnOrderParked(OrderParked $event): void
    {
        $this->parkedOrderIds[] = $event->orderId();
        $this->activeOrderId = null;
        $this->state = SessionState::Idle;
    }

    private function applyOnOrderResumed(OrderResumed $event): void
    {
        $this->activeOrderId = $event->orderId();
        $this->state = SessionState::Building;

        $this->parkedOrderIds = array_filter(
            $this->parkedOrderIds,
            fn(OrderId $id) => !$id->sameValueAs($event->orderId())
        );
    }

    private function applyOnSessionEnded(SessionEnded $event): void
    {
        $this->state = SessionState::Idle;
        $this->activeOrderId = null;
    }
}
