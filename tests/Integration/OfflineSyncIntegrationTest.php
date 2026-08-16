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
        $startSessionHandler(new StartSession(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative(),
            \Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId::generateAsString()
        ));

        $handler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $command = new StartNewOrderOffline(
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
        $startSessionHandler(new StartSession(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative(),
            \Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId::generateAsString()
        ));

        $handler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );

        $command = new StartNewOrderOffline(
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
        $startSessionHandler(new StartSession(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative(),
            \Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId::generateAsString()
        ));

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $offlineCommand = new StartNewOrderOffline(
            $sessionId->toNative(),
            $orderId->toNative()
        );
        $offlineHandler($offlineCommand);

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $syncHandler(new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId->toNative(),
            ['foo' => 'bar', 'nested' => ['id' => '123']]
        ));

        $this->assertTrue($this->pendingSyncQueue->isEmpty());
        $this->assertTrue($this->orderingService->draftOrderWasCreated($orderId));

        $context = $this->orderingService->lastDraftOrderContext($orderId);
        $this->assertNotNull($context);
        $this->assertSame(['foo' => 'bar', 'nested' => ['id' => '123']], $context);
    }

    public function test_redelivered_sync_command_with_same_message_uuid_is_not_reprocessed(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();
        $orderId    = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(new StartSession(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative(),
            \Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId::generateAsString()
        ));

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $offlineHandler(new StartNewOrderOffline(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );

        // A real-world retry redelivers as a NEW object carrying the SAME
        // deterministic id — withMessageUuid() returns a clone, so each
        // delivery is built fresh from its own constructor call.
        $firstDelivery = (new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId->toNative()
        ))->withMessageUuid('replay-key-1');
        $secondDelivery = (new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId->toNative()
        ))->withMessageUuid('replay-key-1');

        $syncHandler($firstDelivery);
        $syncHandler($secondDelivery);

        $this->assertSame(1, $this->orderingService->draftOrderCreationCount($orderId));
    }

    public function test_redelivered_sync_command_is_a_noop_after_a_process_restart(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();
        $orderId    = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(new StartSession(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative(),
            \Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId::generateAsString()
        ));

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $offlineHandler(new StartNewOrderOffline(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $syncHandler((new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId->toNative()
        ))->withMessageUuid('replay-key-1'));

        // A process restart rebuilds the in-memory idempotency registry from
        // events, which cannot recover the sync command's id (OrderSyncedOnline
        // does not persist it). The redelivered command must still be a no-op
        // — resolved by the aggregate remembering the order as synced — not an
        // "Order is not in pending sync list" invariant violation, and the
        // draft order must not be created twice.
        $restartRegistry = new IdempotencyRegistry();
        $restartHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $restartRegistry
        );
        $restartHandler((new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId->toNative()
        ))->withMessageUuid('replay-key-1'));

        $this->assertSame(1, $this->orderingService->draftOrderCreationCount($orderId));
        $this->assertTrue($restartRegistry->hasBeenProcessed('replay-key-1'));
    }

    public function test_sync_online_forwards_empty_context_when_omitted(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();
        $orderId    = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(new StartSession(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative(),
            \Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId::generateAsString()
        ));

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $offlineHandler(new StartNewOrderOffline(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $syncHandler(new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        $this->assertTrue($this->orderingService->draftOrderWasCreated($orderId));
        $this->assertSame([], $this->orderingService->lastDraftOrderContext($orderId));
    }

    public function test_sync_online_command_is_idempotent(): void
    {
        $sessionId  = new SessionId();
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();
        $orderId    = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(new StartSession(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative(),
            \Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId::generateAsString()
        ));

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $offlineHandler(new StartNewOrderOffline(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $syncCommand = new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId->toNative(),
            ['foo' => 'bar', 'nested' => ['id' => '123']]
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
        $startSessionHandler(new StartSession(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative(),
            \Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId::generateAsString()
        ));

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );

        $offlineHandler(new StartNewOrderOffline(
            $sessionId->toNative(),
            $orderId1->toNative()
        ));

        $offlineHandler(new StartNewOrderOffline(
            $sessionId->toNative(),
            $orderId2->toNative()
        ));

        $this->assertSame(2, $this->pendingSyncQueue->count());

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $syncHandler(new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId1->toNative(),
            ['foo' => 'bar', 'nested' => ['id' => '123']]
        ));

        $this->assertSame(1, $this->pendingSyncQueue->count());

        $syncHandler(new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId2->toNative(),
            ['foo' => 'bar', 'nested' => ['id' => '123']]
        ));

        $this->assertTrue($this->pendingSyncQueue->isEmpty());
    }
}
