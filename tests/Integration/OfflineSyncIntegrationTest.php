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
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCreatedOffline;
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
    private InMemoryEventStore $eventStore;
    private InMemoryPosSessionRepository $sessionRepository;
    private PendingSyncQueue $pendingSyncQueue;
    private IdempotencyRegistry $idempotencyRegistry;
    private StubOrderingService $orderingService;

    protected function setUp(): void
    {
        $this->eventStore          = new InMemoryEventStore();
        $this->sessionRepository   = new InMemoryPosSessionRepository($this->eventStore);
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
        // does not persist it). The redelivered command must succeed — resolved
        // by the aggregate remembering the order as synced — not throw an
        // "Order is not in pending sync list" invariant violation. Delivery to
        // the ordering port is at-least-once: the port IS re-invoked (healing a
        // possible earlier failure between store() and createDraftOrder()) and
        // is idempotent per order id by contract.
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

        $this->assertSame(2, $this->orderingService->draftOrderCreationCount($orderId));
        $this->assertTrue($restartRegistry->hasBeenProcessed('replay-key-1'));
    }

    public function test_a_redelivered_offline_create_is_a_noop_after_a_process_restart(): void
    {
        // Same shape as the sync case: a restart rebuilds the registry from
        // events, which cannot recover the create command's id once the order
        // has been synced and left the pending queue. The redelivery must not
        // create a second order behind the same id, and must not fail forever
        // either — the order already exists and is accounted for.
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $create = (new StartNewOrderOffline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('offline-key-1');
        $offlineHandler($create);

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $syncHandler((new SyncOrderOnline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('sync-key-1'));
        $this->assertSame(0, $this->pendingSyncQueue->count());

        $restartRegistry = new IdempotencyRegistry();
        $restartHandler  = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $restartRegistry
        );
        $restartHandler($create);

        // Nothing re-created, nothing re-queued, and the redelivery is now
        // recorded so a third one short-circuits.
        $this->assertSame(0, $this->pendingSyncQueue->count());
        $this->assertTrue($restartRegistry->hasBeenProcessed('offline-key-1'));
        $this->assertSame(1, $this->countOfflineCreations($sessionId, $orderId));
    }

    public function test_an_offline_create_cannot_reuse_an_order_id(): void
    {
        // A genuine reuse — a different command, same id — is refused, unlike
        // the redelivery above. The session has already used that id.
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $offlineHandler((new StartNewOrderOffline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('offline-key-1'));
        (new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        ))((new SyncOrderOnline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('sync-key-1'));

        $this->expectException(\Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException::class);
        $this->expectExceptionMessage('Order id has already been used in this session');

        // A DIFFERENT command carrying the same order id — through the
        // handler, which is where the redelivery no-op lives and where a
        // reuse must still be refused.
        $offlineHandler((new StartNewOrderOffline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('offline-key-2'));
    }

    private function startSession(SessionId $sessionId): void
    {
        (new StartSessionHandler($this->sessionRepository))(new StartSession(
            $sessionId->toNative(),
            (new ShiftId())->toNative(),
            (new TerminalId())->toNative(),
            \Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId::generateAsString()
        ));
    }

    private function countOfflineCreations(SessionId $sessionId, OrderId $orderId): int
    {
        $creations = array_filter(
            $this->eventStore->loadEvents($sessionId->toNative()),
            static fn ($event): bool => $event instanceof OrderCreatedOffline
                && $event->getOrderId()->sameValueAs($orderId)
        );

        return count($creations);
    }

    public function test_a_reuse_is_refused_while_the_order_is_still_pending_sync(): void
    {
        // Pending sync is where an offline order spends most of its life, and
        // the queue guard used to answer "is this order queued" — which a
        // different command reusing the id passes just as well as the command
        // that queued it.
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $offlineHandler((new StartNewOrderOffline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('offline-key-1'));
        $this->assertSame(1, $this->pendingSyncQueue->count());

        $this->expectException(\Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException::class);
        $this->expectExceptionMessage('Order id has already been used in this session');

        $offlineHandler((new StartNewOrderOffline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('offline-key-2'));
    }

    public function test_a_redelivery_while_pending_is_absorbed(): void
    {
        // The same command arriving twice before the sync must NOT be refused
        // and must not create a second order.
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);

        $offlineHandler = new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $create = (new StartNewOrderOffline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('offline-key-1');
        $offlineHandler($create);
        $offlineHandler($create);

        $this->assertSame(1, $this->pendingSyncQueue->count());
        $this->assertSame(1, $this->countOfflineCreations($sessionId, $orderId));
    }

    public function test_a_redelivery_requeues_an_order_whose_enqueue_was_lost(): void
    {
        // The crash this heals: the create was stored, then the process died
        // before the queue entry was written. A host with a persistent queue
        // comes back with the order created but not queued — nothing would
        // ever sync it. The redelivery puts it back.
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);

        $create = (new StartNewOrderOffline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('offline-key-1');
        (new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        ))($create);

        // Everything the crash would have lost: the queue entry and the
        // registry, but not the stored event.
        $lostQueue    = new PendingSyncQueue();
        $lostRegistry = new IdempotencyRegistry();

        (new StartNewOrderOfflineHandler($this->sessionRepository, $lostQueue, $lostRegistry))($create);

        $this->assertSame(1, $lostQueue->count(), 'The order is queued again');
        $this->assertTrue($lostQueue->hasByOrderId($orderId));
        $this->assertSame(1, $this->countOfflineCreations($sessionId, $orderId), 'No second order');
    }

    public function test_one_command_id_cannot_be_spent_on_both_a_create_and_a_sync(): void
    {
        // The trap deterministic ids invite: "one key per order". The create
        // marks the key processed; the sync carrying it used to return early,
        // so no draft order ever reached the Ordering context and the order
        // sat in the queue forever — reported as success.
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);

        (new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        ))((new StartNewOrderOffline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('one-key-per-order'));

        try {
            (new SyncOrderOnlineHandler(
                $this->sessionRepository,
                $this->orderingService,
                $this->pendingSyncQueue,
                $this->idempotencyRegistry
            ))((new SyncOrderOnline($sessionId->toNative(), $orderId->toNative()))
                ->withMessageUuid('one-key-per-order'));
            $this->fail('Expected the reused command id to be refused');
        } catch (\Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException $refusal) {
            $this->assertStringContainsString('cannot be reused', $refusal->getMessage());
        }

        // The order is still queued and still unsynced — loudly, not silently.
        $this->assertTrue($this->pendingSyncQueue->hasByOrderId($orderId));
        $this->assertFalse($this->orderingService->draftOrderWasCreated($orderId));
    }

    public function test_an_unrelated_command_cannot_ride_an_already_synced_order(): void
    {
        // The sync path's mirror of the create-path reuse: a command that
        // never synced anything, naming an order that happens to be synced,
        // used to be absorbed as success and re-issue the draft-order call.
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->startSession($sessionId);

        (new StartNewOrderOfflineHandler(
            $this->sessionRepository,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        ))((new StartNewOrderOffline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('create-key'));

        $syncHandler = new SyncOrderOnlineHandler(
            $this->sessionRepository,
            $this->orderingService,
            $this->pendingSyncQueue,
            $this->idempotencyRegistry
        );
        $syncHandler((new SyncOrderOnline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('sync-key-1'));
        $creationsAfterSync = $this->orderingService->draftOrderCreationCount($orderId);

        $this->expectException(\Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException::class);
        $this->expectExceptionMessage('Order is not in pending sync list');

        $syncHandler((new SyncOrderOnline($sessionId->toNative(), $orderId->toNative()))
            ->withMessageUuid('a-different-command'));
        $this->assertSame($creationsAfterSync, $this->orderingService->draftOrderCreationCount($orderId));
    }

    public function test_redelivery_heals_a_sync_that_failed_after_the_event_was_stored(): void
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

        // Ordering BC fails AFTER the sync event was durably stored: the
        // attempt throws (loud), nothing is marked processed, and the order
        // is left synced-but-draftless.
        $this->orderingService->failNextDraftOrderCreation(new \RuntimeException('Ordering BC unavailable'));
        try {
            $syncHandler((new SyncOrderOnline(
                $sessionId->toNative(),
                $orderId->toNative()
            ))->withMessageUuid('replay-key-1'));
            $this->fail('Expected the Ordering BC failure to propagate');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Ordering BC unavailable', $exception->getMessage());
        }
        $this->assertFalse($this->orderingService->draftOrderWasCreated($orderId));
        $this->assertFalse($this->idempotencyRegistry->hasBeenProcessed('replay-key-1'));

        // The retry (same deterministic id) must HEAL the window: the draft
        // order is finally created, the queue is drained, and the command is
        // marked processed — never a silent skip that strands the order.
        $syncHandler((new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId->toNative()
        ))->withMessageUuid('replay-key-1'));

        $this->assertTrue($this->orderingService->draftOrderWasCreated($orderId));
        $this->assertTrue($this->pendingSyncQueue->isEmpty());
        $this->assertTrue($this->idempotencyRegistry->hasBeenProcessed('replay-key-1'));
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
