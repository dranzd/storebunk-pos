<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Infrastructure\PosSession\ReadModel;

use Dranzd\StorebunkPos\Application\PosSession\ReadModel\PosSessionReadModelInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\NewOrderStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCancelledViaPOS;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCompleted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderParked;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderResumed;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionEnded;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;

final class InMemoryPosSessionReadModel implements PosSessionReadModelInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $sessions = [];

    final public function onSessionStarted(SessionStarted $event): void
    {
        $this->sessions[$event->getSessionId()->toNative()] = [
            'session_id'       => $event->getSessionId()->toNative(),
            'shift_id'         => $event->getShiftId()->toNative(),
            'terminal_id'      => $event->getTerminalId()->toNative(),
            'active_order_id'  => null,
            'last_activity_at' => $event->getStartedAt(),
            'ended'            => false,
        ];
    }

    final public function onNewOrderStarted(NewOrderStarted $event): void
    {
        $sessionId = $event->getSessionId()->toNative();
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['active_order_id']  = $event->getOrderId()->toNative();
            $this->sessions[$sessionId]['last_activity_at'] = $event->getStartedAt();
        }
    }

    final public function onOrderParked(OrderParked $event): void
    {
        $sessionId = $event->getSessionId()->toNative();
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['active_order_id']  = null;
            $this->sessions[$sessionId]['last_activity_at'] = $event->getParkedAt();
        }
    }

    final public function onOrderResumed(OrderResumed $event): void
    {
        $sessionId = $event->getSessionId()->toNative();
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['active_order_id']  = $event->getOrderId()->toNative();
            $this->sessions[$sessionId]['last_activity_at'] = $event->getResumedAt();
        }
    }

    final public function onOrderCompleted(OrderCompleted $event): void
    {
        $sessionId = $event->getSessionId()->toNative();
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['active_order_id']  = null;
            $this->sessions[$sessionId]['last_activity_at'] = $event->getCompletedAt();
        }
    }

    final public function onOrderCancelledViaPOS(OrderCancelledViaPOS $event): void
    {
        $sessionId = $event->getSessionId()->toNative();
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['active_order_id']  = null;
            $this->sessions[$sessionId]['last_activity_at'] = $event->getCancelledAt();
        }
    }

    final public function onSessionEnded(SessionEnded $event): void
    {
        $sessionId = $event->getSessionId()->toNative();
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['ended']            = true;
            $this->sessions[$sessionId]['active_order_id']  = null;
            $this->sessions[$sessionId]['last_activity_at'] = $event->getEndedAt();
        }
    }

    /**
     * @return array<int, array{session_id: string, last_activity_at: \DateTimeImmutable}>
     */
    final public function getSessionsWithActiveOrder(): array
    {
        /** @var array<int, array{session_id: string, last_activity_at: \DateTimeImmutable}> */
        return array_values(
            array_filter(
                $this->sessions,
                fn(array $session) => $session['active_order_id'] !== null && !$session['ended']
            )
        );
    }

    final public function findActiveByShiftId(string $shiftId): array
    {
        $active = array_filter(
            $this->sessions,
            fn(array $session) => $session['shift_id'] === $shiftId && !$session['ended']
        );

        return array_values(array_map(
            fn(array $session) => $session['session_id'],
            $active
        ));
    }
}
