<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\InitiateCheckoutHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\InitiateCheckout;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\CheckoutInitiated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;
use Dranzd\StorebunkPos\Domain\Service\OrderingServiceInterface;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class InitiateCheckoutHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryPosSessionRepository $sessionRepository;
    private OrderingServiceInterface $orderingService;
    private InventoryServiceInterface $inventoryService;
    private InitiateCheckoutHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore        = new InMemoryEventStore();
        $this->sessionRepository = new InMemoryPosSessionRepository($this->eventStore);
        $this->orderingService   = $this->createMock(OrderingServiceInterface::class);
        $this->inventoryService  = $this->createMock(InventoryServiceInterface::class);
        $this->handler           = new InitiateCheckoutHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->inventoryService
        );
    }

    public function test_initiates_checkout_and_confirms_order_and_reservation(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSessionWithOrder($sessionId, $orderId);

        $this->orderingService
            ->expects($this->once())
            ->method('confirmOrder')
            ->with($this->callback(fn (OrderId $id) => $id->toNative() === $orderId->toNative()));

        $this->inventoryService
            ->expects($this->once())
            ->method('confirmReservation')
            ->with($this->callback(fn (OrderId $id) => $id->toNative() === $orderId->toNative()));

        ($this->handler)(InitiateCheckout::forSession($sessionId->toNative()));

        $initiated = array_values(array_filter(
            $this->eventStore->loadEvents($sessionId->toNative()),
            fn ($e) => $e instanceof CheckoutInitiated
        ));

        $this->assertCount(1, $initiated);
        $this->assertSame($sessionId->toNative(), $initiated[0]->getSessionId()->toNative());
    }

    public function test_throws_when_no_active_order(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();

        $startSession = new StartSessionHandler($this->sessionRepository);
        $startSession(StartSession::onTerminal(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative()
        ));

        $this->orderingService->expects($this->never())->method('confirmOrder');
        $this->inventoryService->expects($this->never())->method('confirmReservation');

        $this->expectException(InvariantViolationException::class);

        ($this->handler)(InitiateCheckout::forSession($sessionId->toNative()));
    }

    private function startSessionWithOrder(SessionId $sessionId, OrderId $orderId): void
    {
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();

        $startSession = new StartSessionHandler($this->sessionRepository);
        $startSession(StartSession::onTerminal(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative()
        ));

        $startOrder = new StartNewOrderHandler($this->sessionRepository);
        $startOrder(StartNewOrder::withOrder(
            $sessionId->toNative(),
            $orderId->toNative()
        ));
    }
}
