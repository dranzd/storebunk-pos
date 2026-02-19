<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\DeactivateOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\DeactivateOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderDeactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class DeactivateOrderHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryPosSessionRepository $sessionRepository;
    private DeactivateOrderHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->sessionRepository = new InMemoryPosSessionRepository($this->eventStore);
        $this->handler = new DeactivateOrderHandler($this->sessionRepository);
    }

    public function test_deactivates_active_order_and_records_event(): void
    {
        $sessionId = new SessionId();
        $orderId = new OrderId();

        $this->startSessionWithOrder($sessionId, $orderId);

        ($this->handler)(new DeactivateOrder($sessionId, 'TTL expired'));

        $deactivated = array_values(array_filter(
            $this->eventStore->loadEvents($sessionId->toNative()),
            fn ($e) => $e instanceof OrderDeactivated
        ));

        $this->assertCount(1, $deactivated);
        $this->assertSame($sessionId->toNative(), $deactivated[0]->sessionId()->toNative());
        $this->assertSame($orderId->toNative(), $deactivated[0]->orderId()->toNative());
        $this->assertSame('TTL expired', $deactivated[0]->reason());
    }

    public function test_deactivated_session_returns_to_idle(): void
    {
        $sessionId = new SessionId();
        $orderId = new OrderId();

        $this->startSessionWithOrder($sessionId, $orderId);

        ($this->handler)(new DeactivateOrder($sessionId, 'TTL expired'));

        $this->expectNotToPerformAssertions();
    }

    public function test_deactivating_when_no_active_order_throws(): void
    {
        $sessionId = new SessionId();
        $shiftId = new ShiftId();
        $terminalId = new TerminalId();

        $startSession = new StartSessionHandler($this->sessionRepository);
        $startSession(new StartSession($sessionId, $shiftId, $terminalId));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order to deactivate');

        ($this->handler)(new DeactivateOrder($sessionId, 'TTL expired'));
    }

    private function startSessionWithOrder(SessionId $sessionId, OrderId $orderId): void
    {
        $shiftId = new ShiftId();
        $terminalId = new TerminalId();

        $startSession = new StartSessionHandler($this->sessionRepository);
        $startSession(new StartSession($sessionId, $shiftId, $terminalId));

        $startOrder = new StartNewOrderHandler($this->sessionRepository);
        $startOrder(new StartNewOrder($sessionId, $orderId));
    }
}
