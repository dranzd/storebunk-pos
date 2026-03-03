<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Integration;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderOfflineHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\SyncOrderOnlineHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrderOffline;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Application\PosSession\Command\SyncOrderOnline;
use Dranzd\StorebunkPos\Application\Shared\IdempotencyRegistry;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\PendingSyncQueue;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Tests\Stub\Service\StubOrderingService;
use PHPUnit\Framework\TestCase;

final class OfflineSyncIntegrationTest extends TestCase
{
    private InMemoryPosSessionRepository $sessionRepository;
    private PendingSyncQueue $pendingSyncQueue;
    private IdempotencyRegistry $idempotencyRegistry;
    private StubOrderingService $orderingService;

    protected function setUp(): void
    {
        $eventStore = new InMemoryEventStore();
        $this->sessionRepository   = new InMemoryPosSessionRepository($eventStore);
        $this->pendingSyncQueue    = new PendingSyncQueue();
        $this->idempotencyRegistry = new IdempotencyRegistry();
        $this->orderingService     = new StubOrderingService();
    }

    public function test_offline_order_is_queued_for_sync(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();
        $orderId    = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(StartSession::onTerminal(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative()
        ));

        $handler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $command = StartNewOrderOffline::withOrder(
            $sessionId->toNative(),
            $orderId->toNative()
        );
        $handler($command);

        $this->assertSame(1, $this->pendingSyncQueue->count());
        $this->assertTrue($this->pendingSyncQueue->hasByOrderId($orderId));
    }

    public function test_offline_command_is_idempotent(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();
        $orderId    = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(StartSession::onTerminal(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative()
        ));

        $handler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );

        $command = StartNewOrderOffline::withOrder(
            $sessionId->toNative(),
            $orderId->toNative()
        );
        $handler($command);
        $handler($command);

        $this->assertSame(1, $this->pendingSyncQueue->count());
    }

    public function test_sync_online_removes_from_pending_queue(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();
        $orderId    = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(StartSession::onTerminal(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative()
        ));

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $offlineCommand = StartNewOrderOffline::withOrder(
            $sessionId->toNative(),
            $orderId->toNative()
        );
        $offlineHandler($offlineCommand);

        $session = $this->sessionRepository->load($sessionId);
        $session->markOrderPendingSync($orderId);
        $this->sessionRepository->store($session);

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $syncHandler(SyncOrderOnline::forOrder(
            $sessionId->toNative(),
            $orderId->toNative(),
            'branch-uuid-1',
            null
        ));

        $this->assertTrue($this->pendingSyncQueue->isEmpty());
        $this->assertTrue($this->orderingService->draftOrderWasCreated($orderId));
    }

    public function test_sync_online_command_is_idempotent(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();
        $orderId    = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(StartSession::onTerminal(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative()
        ));

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $offlineHandler(StartNewOrderOffline::withOrder(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        $session = $this->sessionRepository->load($sessionId);
        $session->markOrderPendingSync($orderId);
        $this->sessionRepository->store($session);

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $syncCommand = SyncOrderOnline::forOrder(
            $sessionId->toNative(),
            $orderId->toNative(),
            'branch-uuid-1',
            null
        );
        $syncHandler($syncCommand);
        $syncHandler($syncCommand);

        $this->assertSame(1, $this->orderingService->draftOrderCreationCount($orderId));
    }

    public function test_multiple_offline_orders_sync_independently(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();
        $orderId1   = new OrderId();
        $orderId2   = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(StartSession::onTerminal(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative()
        ));

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );

        $offlineHandler(StartNewOrderOffline::withOrder(
            $sessionId->toNative(),
            $orderId1->toNative()
        ));

        $session = $this->sessionRepository->load($sessionId);
        $session->markOrderPendingSync($orderId1);
        $this->sessionRepository->store($session);

        $offlineHandler(StartNewOrderOffline::withOrder(
            $sessionId->toNative(),
            $orderId2->toNative()
        ));

        $session = $this->sessionRepository->load($sessionId);
        $session->markOrderPendingSync($orderId2);
        $this->sessionRepository->store($session);

        $this->assertSame(2, $this->pendingSyncQueue->count());

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $syncHandler(SyncOrderOnline::forOrder(
            $sessionId->toNative(),
            $orderId1->toNative(),
            'branch-uuid-1',
            null
        ));

        $this->assertSame(1, $this->pendingSyncQueue->count());

        $syncHandler(SyncOrderOnline::forOrder(
            $sessionId->toNative(),
            $orderId2->toNative(),
            'branch-uuid-1',
            null
        ));

        $this->assertTrue($this->pendingSyncQueue->isEmpty());
    }
}
