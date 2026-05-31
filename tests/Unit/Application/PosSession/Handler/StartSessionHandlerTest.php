<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use PHPUnit\Framework\TestCase;

final class StartSessionHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryPosSessionRepository $sessionRepository;
    private StartSessionHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore        = new InMemoryEventStore();
        $this->sessionRepository = new InMemoryPosSessionRepository($this->eventStore);
        $this->handler           = new StartSessionHandler($this->sessionRepository);
    }

    public function test_starts_session_and_records_operating_cashier(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();
        $cashierId  = new CashierId();

        ($this->handler)(StartSession::onTerminalForCashier(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative(),
            $cashierId->toNative()
        ));

        $started = array_values(array_filter(
            $this->eventStore->loadEvents($sessionId->toNative()),
            fn ($e) => $e instanceof SessionStarted
        ));

        $this->assertCount(1, $started);
        $this->assertSame($sessionId->toNative(), $started[0]->getSessionId()->toNative());
        $this->assertSame($cashierId->toNative(), $started[0]->getCashierId()->toNative());
    }

    public function test_operator_survives_the_aggregate_being_reconstituted(): void
    {
        $sessionId = new SessionId();
        $cashierId = new CashierId();

        ($this->handler)(StartSession::onTerminalForCashier(
            $sessionId->toNative(),
            (new ShiftId())->toNative(),
            (new TerminalId())->toNative(),
            $cashierId->toNative()
        ));

        // Reload from the event store: the operator must rehydrate onto the aggregate.
        $session = $this->sessionRepository->load($sessionId);

        $this->assertSame($cashierId->toNative(), $session->cashierId()->toNative());
    }
}
