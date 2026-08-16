<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ParkOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ResumeOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\ParkOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\ResumeOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderResumed;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class ResumeOrderHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryPosSessionRepository $sessionRepository;
    private ResumeOrderHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->sessionRepository = new InMemoryPosSessionRepository($this->eventStore);
        $this->handler = new ResumeOrderHandler($this->sessionRepository);
    }

    public function test_resumes_a_parked_order(): void
    {
        $sessionId = new SessionId();
        $orderId = new OrderId();
        $this->startSessionWithParkedOrder($sessionId, $orderId);

        ($this->handler)(new ResumeOrder($sessionId->toNative(), $orderId->toNative()));

        $resumed = array_values(array_filter(
            $this->eventStore->loadEvents($sessionId->toNative()),
            fn ($e) => $e instanceof OrderResumed
        ));

        $this->assertCount(1, $resumed);
        $this->assertSame($orderId->toNative(), $resumed[0]->getOrderId()->toNative());
    }

    public function test_refuses_to_resume_an_order_that_is_not_parked(): void
    {
        $sessionId = new SessionId();
        $orderId = new OrderId();
        $this->startSessionWithParkedOrder($sessionId, $orderId);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Order is not in parked list');

        ($this->handler)(new ResumeOrder($sessionId->toNative(), (new OrderId())->toNative()));
    }

    public function test_refuses_to_resume_while_another_order_is_active(): void
    {
        $sessionId = new SessionId();
        $parkedOrderId = new OrderId();
        $this->startSessionWithParkedOrder($sessionId, $parkedOrderId);

        $startOrder = new StartNewOrderHandler($this->sessionRepository);
        $startOrder(new StartNewOrder($sessionId->toNative(), (new OrderId())->toNative()));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot resume order when an order is already active');

        ($this->handler)(new ResumeOrder($sessionId->toNative(), $parkedOrderId->toNative()));
    }

    private function startSessionWithParkedOrder(SessionId $sessionId, OrderId $orderId): void
    {
        $startSession = new StartSessionHandler($this->sessionRepository);
        $startSession(new StartSession(
            $sessionId->toNative(),
            (new ShiftId())->toNative(),
            (new TerminalId())->toNative(),
            CashierId::generateAsString()
        ));

        $startOrder = new StartNewOrderHandler($this->sessionRepository);
        $startOrder(new StartNewOrder($sessionId->toNative(), $orderId->toNative()));

        $park = new ParkOrderHandler($this->sessionRepository);
        $park(new ParkOrder($sessionId->toNative()));
    }
}
