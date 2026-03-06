<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\CancelOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\CancelOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCancelledViaPOS;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;
use Dranzd\StorebunkPos\Domain\Service\OrderingServiceInterface;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class CancelOrderHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryPosSessionRepository $sessionRepository;
    private OrderingServiceInterface $orderingService;
    private InventoryServiceInterface $inventoryService;
    private CancelOrderHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore        = new InMemoryEventStore();
        $this->sessionRepository = new InMemoryPosSessionRepository($this->eventStore);
        $this->orderingService   = $this->createMock(OrderingServiceInterface::class);
        $this->inventoryService  = $this->createMock(InventoryServiceInterface::class);
        $this->handler           = new CancelOrderHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->inventoryService
        );
    }

    public function test_cancels_active_order_and_notifies_external_services(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSessionWithOrder($sessionId, $orderId);

        $this->orderingService
            ->expects($this->once())
            ->method('cancelOrder')
            ->with(
                $this->callback(fn (OrderId $id) => $id->toNative() === $orderId->toNative()),
                'customer request'
            );

        $this->inventoryService
            ->expects($this->once())
            ->method('releaseReservation')
            ->with($this->callback(fn (OrderId $id) => $id->toNative() === $orderId->toNative()));

        ($this->handler)(CancelOrder::because($sessionId->toNative(), 'customer request'));

        $cancelled = array_values(array_filter(
            $this->eventStore->loadEvents($sessionId->toNative()),
            fn ($e) => $e instanceof OrderCancelledViaPOS
        ));

        $this->assertCount(1, $cancelled);
        $this->assertSame($sessionId->toNative(), $cancelled[0]->getSessionId()->toNative());
        $this->assertSame('customer request', $cancelled[0]->getReason());
    }

    public function test_throws_and_does_not_call_services_when_no_active_order(): void
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

        $this->orderingService->expects($this->never())->method('cancelOrder');
        $this->inventoryService->expects($this->never())->method('releaseReservation');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order to cancel');

        ($this->handler)(CancelOrder::because($sessionId->toNative(), 'idle cancel'));
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
