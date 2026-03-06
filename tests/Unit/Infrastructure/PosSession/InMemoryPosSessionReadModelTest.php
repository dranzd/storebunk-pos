<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Infrastructure\PosSession;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\NewOrderStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCreatedOffline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderDeactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderMarkedPendingSync;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderReactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\PosSession\ReadModel\InMemoryPosSessionReadModel;
use PHPUnit\Framework\TestCase;

final class InMemoryPosSessionReadModelTest extends TestCase
{
    private InMemoryPosSessionReadModel $readModel;

    protected function setUp(): void
    {
        $this->readModel = new InMemoryPosSessionReadModel();
    }

    public function test_it_projects_session_started_event(): void
    {
        $sessionId = new SessionId();
        $shiftId   = new ShiftId();

        $this->startSession($sessionId, $shiftId);

        $sessions = $this->readModel->findActiveByShiftId($shiftId->toNative());
        $this->assertCount(1, $sessions);
        $this->assertSame($sessionId->toNative(), $sessions[0]);
    }

    public function test_it_projects_new_order_started_event(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);

        $event = NewOrderStarted::occur($sessionId, $orderId, new DateTimeImmutable());
        $this->readModel->onNewOrderStarted($event);

        $active = $this->readModel->getSessionsWithActiveOrder();
        $this->assertCount(1, $active);
        $this->assertSame($sessionId->toNative(), $active[0]['session_id']);
    }

    public function test_order_deactivated_removes_session_from_active_order_list(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);
        $this->readModel->onNewOrderStarted(
            NewOrderStarted::occur($sessionId, $orderId, new DateTimeImmutable())
        );

        $this->assertCount(1, $this->readModel->getSessionsWithActiveOrder());

        $event = OrderDeactivated::occur($sessionId, $orderId, 'ttl_expired', new DateTimeImmutable());
        $this->readModel->onOrderDeactivated($event);

        $this->assertCount(0, $this->readModel->getSessionsWithActiveOrder());
    }

    public function test_order_reactivated_restores_session_to_active_order_list(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);
        $this->readModel->onNewOrderStarted(
            NewOrderStarted::occur($sessionId, $orderId, new DateTimeImmutable())
        );
        $this->readModel->onOrderDeactivated(
            OrderDeactivated::occur($sessionId, $orderId, 'ttl_expired', new DateTimeImmutable())
        );

        $this->assertCount(0, $this->readModel->getSessionsWithActiveOrder());

        $event = OrderReactivated::occur($sessionId, $orderId, new DateTimeImmutable());
        $this->readModel->onOrderReactivated($event);

        $active = $this->readModel->getSessionsWithActiveOrder();
        $this->assertCount(1, $active);
        $this->assertSame($sessionId->toNative(), $active[0]['session_id']);
    }

    public function test_order_created_offline_adds_session_to_active_order_list(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);

        $this->assertCount(0, $this->readModel->getSessionsWithActiveOrder());

        $event = OrderCreatedOffline::occur($sessionId, $orderId, 'cmd-uuid-1');
        $this->readModel->onOrderCreatedOffline($event);

        $active = $this->readModel->getSessionsWithActiveOrder();
        $this->assertCount(1, $active);
        $this->assertSame($sessionId->toNative(), $active[0]['session_id']);
    }

    public function test_order_marked_pending_sync_removes_session_from_active_order_list(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);
        $this->readModel->onOrderCreatedOffline(
            OrderCreatedOffline::occur($sessionId, $orderId, 'cmd-uuid-1')
        );

        $this->assertCount(1, $this->readModel->getSessionsWithActiveOrder());

        $event = OrderMarkedPendingSync::occur($sessionId, $orderId);
        $this->readModel->onOrderMarkedPendingSync($event);

        $this->assertCount(0, $this->readModel->getSessionsWithActiveOrder());
    }

    public function test_it_finds_active_sessions_by_shift_id(): void
    {
        $shiftId1  = new ShiftId();
        $shiftId2  = new ShiftId();
        $session1  = new SessionId();
        $session2  = new SessionId();
        $session3  = new SessionId();

        $this->startSession($session1, $shiftId1);
        $this->startSession($session2, $shiftId1);
        $this->startSession($session3, $shiftId2);

        $results = $this->readModel->findActiveByShiftId($shiftId1->toNative());
        $this->assertCount(2, $results);
        $this->assertContains($session1->toNative(), $results);
        $this->assertContains($session2->toNative(), $results);
    }

    private function startSession(SessionId $sessionId, ?ShiftId $shiftId = null): void
    {
        $event = SessionStarted::occur(
            $sessionId,
            $shiftId ?? new ShiftId(),
            new TerminalId(),
            new DateTimeImmutable()
        );
        $this->readModel->onSessionStarted($event);
    }
}
