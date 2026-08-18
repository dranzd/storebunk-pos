<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\EndSession;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\EndSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionEnded;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class EndSessionHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryPosSessionRepository $sessionRepository;
    private EndSessionHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->sessionRepository = new InMemoryPosSessionRepository($this->eventStore);
        $this->handler = new EndSessionHandler($this->sessionRepository);
    }

    public function test_ends_a_session_with_no_active_order(): void
    {
        $sessionId = new SessionId();
        $this->startSession($sessionId);

        ($this->handler)(new EndSession($sessionId->toNative()));

        $ended = array_values(array_filter(
            $this->eventStore->loadEvents($sessionId->toNative()),
            fn ($e) => $e instanceof SessionEnded
        ));

        $this->assertCount(1, $ended);
        $this->assertSame($sessionId->toNative(), $ended[0]->getSessionId()->toNative());
    }

    public function test_refuses_to_end_a_session_with_an_active_order(): void
    {
        $sessionId = new SessionId();
        $this->startSession($sessionId);

        $startOrder = new StartNewOrderHandler($this->sessionRepository);
        $startOrder(new StartNewOrder($sessionId->toNative(), (new OrderId())->toNative()));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot end session with an active order');

        ($this->handler)(new EndSession($sessionId->toNative()));
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
}
