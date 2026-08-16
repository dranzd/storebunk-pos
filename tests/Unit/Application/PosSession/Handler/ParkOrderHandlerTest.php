<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ParkOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\ParkOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderParked;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class ParkOrderHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryPosSessionRepository $sessionRepository;
    private ParkOrderHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->sessionRepository = new InMemoryPosSessionRepository($this->eventStore);
        $this->handler = new ParkOrderHandler($this->sessionRepository);
    }

    public function test_parks_the_active_order(): void
    {
        $sessionId = new SessionId();
        $orderId = new OrderId();
        $this->startSessionWithOrder($sessionId, $orderId);

        ($this->handler)(new ParkOrder($sessionId->toNative()));

        $parked = array_values(array_filter(
            $this->eventStore->loadEvents($sessionId->toNative()),
            fn ($e) => $e instanceof OrderParked
        ));

        $this->assertCount(1, $parked);
        $this->assertSame($orderId->toNative(), $parked[0]->getOrderId()->toNative());
    }

    public function test_refuses_to_park_when_no_order_is_active(): void
    {
        $sessionId = new SessionId();
        $this->startSession($sessionId);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order to park');

        ($this->handler)(new ParkOrder($sessionId->toNative()));
    }

    private function startSession(SessionId $sessionId): void
    {
        $startSession = new StartSessionHandler($this->sessionRepository);
        $startSession(new StartSession(
            $sessionId->toNative(),
            (new ShiftId())->toNative(),
            (new TerminalId())->toNative(),
            CashierId::generateAsString()
        ));
    }

    private function startSessionWithOrder(SessionId $sessionId, OrderId $orderId): void
    {
        $this->startSession($sessionId);

        $startOrder = new StartNewOrderHandler($this->sessionRepository);
        $startOrder(new StartNewOrder($sessionId->toNative(), $orderId->toNative()));
    }
}
