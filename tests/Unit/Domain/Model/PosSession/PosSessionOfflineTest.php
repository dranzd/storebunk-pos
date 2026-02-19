<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Model\PosSession;

use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCreatedOffline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderMarkedPendingSync;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderSyncedOnline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\PosSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class PosSessionOfflineTest extends TestCase
{
    private PosSession $session;
    private SessionId $sessionId;

    protected function setUp(): void
    {
        $this->sessionId = new SessionId();
        $this->session = PosSession::start(
            $this->sessionId,
            new ShiftId(),
            new TerminalId()
        );
        $this->session->popRecordedEvents();
    }

    public function test_it_can_start_new_order_offline(): void
    {
        $orderId = new OrderId();
        $commandId = 'cmd-uuid-1';

        $this->session->startNewOrderOffline($orderId, $commandId);

        $events = $this->session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderCreatedOffline::class, $events[0]);
        /** @var OrderCreatedOffline $event */
        $event = $events[0];
        $this->assertSame($commandId, $event->commandId());
    }

    public function test_cannot_start_offline_order_when_order_is_active(): void
    {
        $this->session->startNewOrderOffline(new OrderId(), 'cmd-1');
        $this->session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot start new order when an order is already active');

        $this->session->startNewOrderOffline(new OrderId(), 'cmd-2');
    }

    public function test_it_can_mark_active_order_as_pending_sync(): void
    {
        $orderId = new OrderId();
        $this->session->startNewOrderOffline($orderId, 'cmd-1');
        $this->session->popRecordedEvents();

        $this->session->markOrderPendingSync($orderId);

        $events = $this->session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderMarkedPendingSync::class, $events[0]);
    }

    public function test_cannot_mark_pending_sync_when_no_active_order(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Can only mark the active order as pending sync');

        $this->session->markOrderPendingSync(new OrderId());
    }

    public function test_cannot_mark_pending_sync_for_wrong_order(): void
    {
        $this->session->startNewOrderOffline(new OrderId(), 'cmd-1');
        $this->session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Can only mark the active order as pending sync');

        $this->session->markOrderPendingSync(new OrderId());
    }

    public function test_it_can_sync_pending_order_online(): void
    {
        $orderId = new OrderId();
        $this->session->startNewOrderOffline($orderId, 'cmd-1');
        $this->session->markOrderPendingSync($orderId);
        $this->session->popRecordedEvents();

        $this->session->syncOrderOnline($orderId);

        $events = $this->session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderSyncedOnline::class, $events[0]);
    }

    public function test_cannot_sync_order_not_in_pending_list(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Order is not in pending sync list');

        $this->session->syncOrderOnline(new OrderId());
    }

    public function test_full_offline_to_online_sync_flow(): void
    {
        $orderId = new OrderId();
        $commandId = 'cmd-offline-1';

        $this->session->startNewOrderOffline($orderId, $commandId);
        $this->session->markOrderPendingSync($orderId);
        $this->session->syncOrderOnline($orderId);

        $events = $this->session->popRecordedEvents();
        $this->assertCount(3, $events);
        $this->assertInstanceOf(OrderCreatedOffline::class, $events[0]);
        $this->assertInstanceOf(OrderMarkedPendingSync::class, $events[1]);
        $this->assertInstanceOf(OrderSyncedOnline::class, $events[2]);
    }

    public function test_session_is_idle_after_marking_pending_sync(): void
    {
        $orderId = new OrderId();
        $this->session->startNewOrderOffline($orderId, 'cmd-1');
        $this->session->markOrderPendingSync($orderId);
        $this->session->popRecordedEvents();

        $this->session->startNewOrderOffline(new OrderId(), 'cmd-2');

        $events = $this->session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderCreatedOffline::class, $events[0]);
    }

    public function test_multiple_offline_orders_can_be_queued_and_synced(): void
    {
        $orderId1 = new OrderId();
        $orderId2 = new OrderId();

        $this->session->startNewOrderOffline($orderId1, 'cmd-1');
        $this->session->markOrderPendingSync($orderId1);

        $this->session->startNewOrderOffline($orderId2, 'cmd-2');
        $this->session->markOrderPendingSync($orderId2);

        $this->session->syncOrderOnline($orderId1);
        $this->session->syncOrderOnline($orderId2);

        $events = $this->session->popRecordedEvents();
        $this->assertCount(6, $events);
    }
}
