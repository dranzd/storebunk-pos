<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\CompleteOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\CompleteOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCompleted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;
use Dranzd\StorebunkPos\Domain\Service\OrderingServiceInterface;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class CompleteOrderHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryPosSessionRepository $sessionRepository;
    private OrderingServiceInterface $orderingService;
    private InventoryServiceInterface $inventoryService;
    private CompleteOrderHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore        = new InMemoryEventStore();
        $this->sessionRepository = new InMemoryPosSessionRepository($this->eventStore);
        $this->orderingService   = $this->createMock(OrderingServiceInterface::class);
        $this->inventoryService  = $this->createMock(InventoryServiceInterface::class);
        $this->handler           = new CompleteOrderHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->inventoryService
        );
    }

    public function test_completes_order_and_fulfills_inventory_when_fully_paid(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->buildCheckoutSession($sessionId, $orderId);

        $this->orderingService
            ->method('isOrderFullyPaid')
            ->willReturn(true);

        $this->inventoryService
            ->expects($this->once())
            ->method('fulfillOrderReservation')
            ->with($this->callback(fn (OrderId $id) => $id->toNative() === $orderId->toNative()));

        ($this->handler)(CompleteOrder::forSession($sessionId->toNative()));

        $completed = array_values(array_filter(
            $this->eventStore->loadEvents($sessionId->toNative()),
            fn ($e) => $e instanceof OrderCompleted
        ));

        $this->assertCount(1, $completed);
        $this->assertSame($sessionId->toNative(), $completed[0]->getSessionId()->toNative());
    }

    public function test_throws_when_order_not_fully_paid(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->buildCheckoutSession($sessionId, $orderId);

        $this->orderingService
            ->method('isOrderFullyPaid')
            ->willReturn(false);

        $this->inventoryService
            ->expects($this->never())
            ->method('fulfillOrderReservation');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Order is not fully paid');

        ($this->handler)(CompleteOrder::forSession($sessionId->toNative()));
    }

    private function buildCheckoutSession(SessionId $sessionId, OrderId $orderId): void
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

        $session = $this->sessionRepository->load($sessionId);
        $session->initiateCheckout();
        $this->sessionRepository->store($session);
    }
}
