<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Model\PosSession;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\NewOrderStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderParked;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderResumed;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionEnded;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\PosSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class PosSessionTest extends TestCase
{
    public function test_it_can_be_started(): void
    {
        $sessionId = new SessionId();
        $shiftId = new ShiftId();
        $terminalId = new TerminalId();

        $session = PosSession::start($sessionId, $shiftId, $terminalId);

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SessionStarted::class, $events[0]);

        $event = $events[0];
        assert($event instanceof SessionStarted);
        $this->assertTrue($event->sessionId()->sameValueAs($sessionId));
        $this->assertTrue($event->shiftId()->sameValueAs($shiftId));
        $this->assertTrue($event->terminalId()->sameValueAs($terminalId));
        $this->assertInstanceOf(DateTimeImmutable::class, $event->startedAt());
    }

    public function test_it_can_start_new_order(): void
    {
        $session = $this->createStartedSession();
        $session->popRecordedEvents();

        $orderId = new OrderId();
        $session->startNewOrder($orderId);

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(NewOrderStarted::class, $events[0]);

        $event = $events[0];
        assert($event instanceof NewOrderStarted);
        $this->assertTrue($event->orderId()->sameValueAs($orderId));
        $this->assertInstanceOf(DateTimeImmutable::class, $event->startedAt());
    }

    public function test_it_cannot_start_new_order_when_order_is_active(): void
    {
        $session = $this->createStartedSession();
        $session->startNewOrder(new OrderId());
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot start new order when an order is already active');

        $session->startNewOrder(new OrderId());
    }

    public function test_it_can_park_order(): void
    {
        $session = $this->createStartedSession();
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->popRecordedEvents();

        $session->parkOrder();

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderParked::class, $events[0]);

        $event = $events[0];
        assert($event instanceof OrderParked);
        $this->assertTrue($event->orderId()->sameValueAs($orderId));
        $this->assertInstanceOf(DateTimeImmutable::class, $event->parkedAt());
    }

    public function test_it_cannot_park_order_when_no_active_order(): void
    {
        $session = $this->createStartedSession();
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order to park');

        $session->parkOrder();
    }

    public function test_it_can_resume_parked_order(): void
    {
        $session = $this->createStartedSession();
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->parkOrder();
        $session->popRecordedEvents();

        $session->resumeOrder($orderId);

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderResumed::class, $events[0]);

        $event = $events[0];
        assert($event instanceof OrderResumed);
        $this->assertTrue($event->orderId()->sameValueAs($orderId));
        $this->assertInstanceOf(DateTimeImmutable::class, $event->resumedAt());
    }

    public function test_it_cannot_resume_order_when_order_is_active(): void
    {
        $session = $this->createStartedSession();
        $orderId1 = new OrderId();
        $orderId2 = new OrderId();
        $session->startNewOrder($orderId1);
        $session->parkOrder();
        $session->startNewOrder($orderId2);
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot resume order when an order is already active');

        $session->resumeOrder($orderId1);
    }

    public function test_it_cannot_resume_order_that_is_not_parked(): void
    {
        $session = $this->createStartedSession();
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Order is not in parked list');

        $session->resumeOrder(new OrderId());
    }

    public function test_it_can_end_session(): void
    {
        $session = $this->createStartedSession();
        $session->popRecordedEvents();

        $session->end();

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SessionEnded::class, $events[0]);

        $event = $events[0];
        assert($event instanceof SessionEnded);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->endedAt());
    }

    public function test_it_cannot_end_session_with_active_order(): void
    {
        $session = $this->createStartedSession();
        $session->startNewOrder(new OrderId());
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot end session with an active order');

        $session->end();
    }

    public function test_it_can_initiate_checkout(): void
    {
        $session = $this->createStartedSession();
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->popRecordedEvents();

        $session->initiateCheckout();

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\CheckoutInitiated::class, $events[0]);
    }

    public function test_it_cannot_initiate_checkout_without_active_order(): void
    {
        $session = $this->createStartedSession();
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order to checkout');

        $session->initiateCheckout();
    }

    public function test_it_cannot_initiate_checkout_from_non_building_state(): void
    {
        $session = $this->createStartedSession();
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->initiateCheckout();
        $session->completeOrder();
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order to checkout');

        $session->initiateCheckout();
    }

    public function test_it_can_request_payment(): void
    {
        $session = $this->createStartedSession();
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->initiateCheckout();
        $session->popRecordedEvents();

        $amount = \Dranzd\Common\Domain\ValueObject\Money\Basic::fromArray(['amount' => 10000, 'currency' => 'USD']);
        $session->requestPayment($amount, 'cash');

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\PaymentRequested::class, $events[0]);
    }

    public function test_it_cannot_request_payment_without_active_order(): void
    {
        $session = $this->createStartedSession();
        $session->popRecordedEvents();

        $amount = \Dranzd\Common\Domain\ValueObject\Money\Basic::fromArray(['amount' => 10000, 'currency' => 'USD']);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order for payment');

        $session->requestPayment($amount, 'cash');
    }

    public function test_it_can_complete_order(): void
    {
        $session = $this->createStartedSession();
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->initiateCheckout();
        $session->popRecordedEvents();

        $session->completeOrder();

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCompleted::class, $events[0]);
    }

    public function test_it_cannot_complete_order_without_active_order(): void
    {
        $session = $this->createStartedSession();
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order to complete');

        $session->completeOrder();
    }

    public function test_it_can_cancel_order(): void
    {
        $session = $this->createStartedSession();
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->popRecordedEvents();

        $session->cancelOrder('Customer changed mind');

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCancelledViaPOS::class, $events[0]);

        $event = $events[0];
        assert($event instanceof \Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCancelledViaPOS);
        $this->assertSame('Customer changed mind', $event->reason());
    }

    public function test_it_cannot_cancel_order_without_active_order(): void
    {
        $session = $this->createStartedSession();
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order to cancel');

        $session->cancelOrder('Test reason');
    }

    public function test_it_can_be_reconstituted_from_history(): void
    {
        $sessionId = new SessionId();
        $originalSession = PosSession::start($sessionId, new ShiftId(), new TerminalId());
        $orderId = new OrderId();
        $originalSession->startNewOrder($orderId);
        $originalSession->parkOrder();
        $originalSession->end();
        $events = $originalSession->popRecordedEvents();

        $session = new PosSession();
        $session = $session->reconstituteFromHistory($events);

        $this->assertInstanceOf(PosSession::class, $session);
        $this->assertSame($sessionId->toNative(), $session->getAggregateRootUuid());
        $this->assertSame(4, $session->getAggregateRootVersion());
        $this->assertEmpty($session->popRecordedEvents());
    }

    public function test_full_checkout_flow(): void
    {
        $sessionId = new SessionId();
        $session = PosSession::start($sessionId, new ShiftId(), new TerminalId());
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->initiateCheckout();
        $amount = \Dranzd\Common\Domain\ValueObject\Money\Basic::fromArray(['amount' => 10000, 'currency' => 'USD']);
        $session->requestPayment($amount, 'cash');
        $session->completeOrder();

        $events = $session->popRecordedEvents();
        $this->assertCount(5, $events);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted::class, $events[0]);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\NewOrderStarted::class, $events[1]);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\CheckoutInitiated::class, $events[2]);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\PaymentRequested::class, $events[3]);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCompleted::class, $events[4]);
    }

    public function test_it_can_deactivate_order(): void
    {
        $session = $this->createStartedSession();
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->popRecordedEvents();

        $session->deactivateOrder('TTL expired');

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderDeactivated::class, $events[0]);

        $event = $events[0];
        assert($event instanceof \Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderDeactivated);
        $this->assertSame('TTL expired', $event->reason());
    }

    public function test_it_cannot_deactivate_order_without_active_order(): void
    {
        $session = $this->createStartedSession();
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order to deactivate');

        $session->deactivateOrder('TTL expired');
    }

    public function test_it_can_reactivate_deactivated_order(): void
    {
        $session = $this->createStartedSession();
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->deactivateOrder('TTL expired');
        $session->popRecordedEvents();

        $session->reactivateOrder($orderId);

        $events = $session->popRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderReactivated::class, $events[0]);
    }

    public function test_it_cannot_reactivate_order_when_order_is_active(): void
    {
        $session = $this->createStartedSession();
        $orderId1 = new OrderId();
        $orderId2 = new OrderId();
        $session->startNewOrder($orderId1);
        $session->deactivateOrder('TTL expired');
        $session->startNewOrder($orderId2);
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot reactivate order when an order is already active');

        $session->reactivateOrder($orderId1);
    }

    public function test_it_cannot_reactivate_order_that_is_not_deactivated(): void
    {
        $session = $this->createStartedSession();
        $orderId = new OrderId();
        $session->popRecordedEvents();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Order is not in inactive list');

        $session->reactivateOrder($orderId);
    }

    public function test_full_draft_lifecycle_flow(): void
    {
        $sessionId = new SessionId();
        $session = PosSession::start($sessionId, new ShiftId(), new TerminalId());
        $orderId = new OrderId();
        $session->startNewOrder($orderId);
        $session->deactivateOrder('TTL expired');
        $session->reactivateOrder($orderId);
        $session->initiateCheckout();

        $events = $session->popRecordedEvents();
        $this->assertCount(5, $events);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted::class, $events[0]);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\NewOrderStarted::class, $events[1]);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderDeactivated::class, $events[2]);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderReactivated::class, $events[3]);
        $this->assertInstanceOf(\Dranzd\StorebunkPos\Domain\Model\PosSession\Event\CheckoutInitiated::class, $events[4]);
    }

    private function createStartedSession(): PosSession
    {
        return PosSession::start(
            new SessionId(),
            new ShiftId(),
            new TerminalId()
        );
    }
}
