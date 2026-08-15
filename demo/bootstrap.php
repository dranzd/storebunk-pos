<?php

declare(strict_types=1);

use Dranzd\Common\Cqrs\Infrastructure\Bus\SimpleCommandBus;
use Dranzd\Common\Cqrs\Infrastructure\HandlerRegistry\InMemoryHandlerRegistry;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\EventStore;
use Dranzd\StorebunkPos\Demo\Cli\FileEventStore;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCreatedOffline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderMarkedPendingSync;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderSyncedOnline;
use Dranzd\StorebunkPos\Application\PosSession\Command\CancelOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\CompleteOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\EndSession;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\CancelOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\CompleteOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\EndSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\InitiateCheckoutHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ParkOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ReactivateOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\RequestPaymentHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ResumeOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderOfflineHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\SyncOrderOnlineHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\InitiateCheckout;
use Dranzd\StorebunkPos\Application\PosSession\Command\ParkOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\ReactivateOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\RequestPayment;
use Dranzd\StorebunkPos\Application\PosSession\Command\ResumeOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrderOffline;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Application\PosSession\Command\SyncOrderOnline;
use Dranzd\StorebunkPos\Application\Shared\IdempotencyRegistry;
use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Application\Shift\Command\CloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\ForceCloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\AssignShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\CloseShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\ForceCloseShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\OpenShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\RecordCashDropHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\UnassignShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Application\Shift\Command\RecordCashDrop;
use Dranzd\StorebunkPos\Application\Shift\Command\UnassignShift;
use Dranzd\StorebunkPos\Application\Terminal\Command\ActivateTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\DisableTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\ActivateTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DisableTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RegisterTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\SetTerminalMaintenanceHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\SetTerminalMaintenance;
use Dranzd\StorebunkPos\Domain\Service\PendingSyncQueue;
use Dranzd\StorebunkPos\Domain\Service\ShiftClosePolicy;
use Dranzd\StorebunkPos\Infrastructure\PosSession\ReadModel\InMemoryPosSessionReadModel;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Infrastructure\Shift\Repository\InMemoryShiftRepository;
use Dranzd\StorebunkPos\Infrastructure\Terminal\ReadModel\InMemoryTerminalReadModel;
use Dranzd\StorebunkPos\Infrastructure\Terminal\Repository\InMemoryTerminalRepository;
use Dranzd\StorebunkPos\Tests\Stub\Service\StubInventoryService;
use Dranzd\StorebunkPos\Tests\Stub\Service\StubOrderingService;
use Dranzd\StorebunkPos\Tests\Stub\Service\StubPaymentService;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// ── Event Store (shared across all repositories) ─────────────────────────────
// File-backed: every demo command runs in its own PHP process, so events must
// survive process boundaries for multi-step scenarios to work.
$eventStore = new FileEventStore(FileEventStore::defaultPath());

// ── Repositories ─────────────────────────────────────────────────────────────
$terminalRepository   = new InMemoryTerminalRepository($eventStore);
$shiftRepository      = new InMemoryShiftRepository($eventStore);
$sessionRepository    = new InMemoryPosSessionRepository($eventStore);

// ── Read Models ───────────────────────────────────────────────────────────────
$terminalReadModel    = new InMemoryTerminalReadModel();
$posSessionReadModel  = new InMemoryPosSessionReadModel();

// ── BC Service Stubs ──────────────────────────────────────────────────────────
$orderingService   = new StubOrderingService();
$inventoryService  = new StubInventoryService();
$paymentService    = new StubPaymentService();

// ── Domain Services ───────────────────────────────────────────────────────────
$pendingSyncQueue    = new PendingSyncQueue();
$idempotencyRegistry = new IdempotencyRegistry();
$shiftClosePolicy    = new ShiftClosePolicy();

// ── Rebuild offline-sync state from persisted events ─────────────────────────
// The queue and registry are plain in-memory objects; replay the persisted
// session events so offline orders queued in an earlier process are still
// pending here. Events per aggregate are in append order, so an
// OrderCreatedOffline is always seen before its OrderMarkedPendingSync.
$offlineCommandIdsByOrder = [];
foreach ($eventStore->allEvents() as $aggregateEvents) {
    foreach ($aggregateEvents as $event) {
        if ($event instanceof OrderCreatedOffline) {
            $idempotencyRegistry->markAsProcessed($event->getCommandId());
            $offlineCommandIdsByOrder[$event->getOrderId()->toNative()] = $event->getCommandId();
        } elseif ($event instanceof OrderMarkedPendingSync) {
            $pendingSyncQueue->enqueue(
                $event->getSessionId(),
                $event->getOrderId(),
                $offlineCommandIdsByOrder[$event->getOrderId()->toNative()] ?? ''
            );
        } elseif ($event instanceof OrderSyncedOnline) {
            $pendingSyncQueue->dequeueByOrderId($event->getOrderId());
        }
    }
}

// ── Command Handlers ──────────────────────────────────────────────────────────
$handlers = [
    // Terminal
    RegisterTerminal::class      => new RegisterTerminalHandler($terminalRepository),
    ActivateTerminal::class      => new ActivateTerminalHandler($terminalRepository),
    DisableTerminal::class       => new DisableTerminalHandler($terminalRepository),
    SetTerminalMaintenance::class => new SetTerminalMaintenanceHandler($terminalRepository),

    // Shift
    OpenShift::class        => new OpenShiftHandler($shiftRepository),
    AssignShift::class      => new AssignShiftHandler($shiftRepository),
    UnassignShift::class    => new UnassignShiftHandler($shiftRepository),
    CloseShift::class       => new CloseShiftHandler($shiftRepository, $shiftClosePolicy, $posSessionReadModel),
    ForceCloseShift::class  => new ForceCloseShiftHandler($shiftRepository),
    RecordCashDrop::class   => new RecordCashDropHandler($shiftRepository),

    // PosSession (online)
    StartSession::class     => new StartSessionHandler($sessionRepository),
    StartNewOrder::class    => new StartNewOrderHandler($sessionRepository),
    ParkOrder::class        => new ParkOrderHandler($sessionRepository),
    ResumeOrder::class      => new ResumeOrderHandler($sessionRepository),
    ReactivateOrder::class  => new ReactivateOrderHandler($sessionRepository, $inventoryService),
    InitiateCheckout::class => new InitiateCheckoutHandler($sessionRepository, $orderingService, $inventoryService),
    RequestPayment::class   => new RequestPaymentHandler($sessionRepository, $paymentService),
    CompleteOrder::class    => new CompleteOrderHandler($sessionRepository, $orderingService, $inventoryService),
    CancelOrder::class      => new CancelOrderHandler($sessionRepository, $orderingService, $inventoryService),
    EndSession::class       => new EndSessionHandler($sessionRepository),

    // PosSession (offline/sync)
    StartNewOrderOffline::class => new StartNewOrderOfflineHandler($sessionRepository, $pendingSyncQueue, $idempotencyRegistry),
    SyncOrderOnline::class      => new SyncOrderOnlineHandler($sessionRepository, $orderingService, $pendingSyncQueue, $idempotencyRegistry),
];

// ── Wire read model to event store (projection) ───────────────────────────────
// The InMemoryTerminalReadModel is updated by calling its on* methods after
// each terminal command. We wrap the terminal repository store to also project.
// For the demo we use a simple post-command projection hook.

// ── Command Bus ───────────────────────────────────────────────────────────────
$registry   = new InMemoryHandlerRegistry();
foreach ($handlers as $messageName => $handler) {
    $registry->register($messageName, $handler);
}
$commandBus = new SimpleCommandBus($registry);

// ── Terminal Read Model Projection Helper ─────────────────────────────────────
// After each terminal command we replay all terminal events into the read model.
// This is a simple approach suitable for a demo (not production).
function projectTerminalReadModel(
    EventStore $eventStore,
    InMemoryTerminalReadModel $readModel,
    string $terminalId
): void {
    if (!$eventStore->hasEvents($terminalId)) {
        return;
    }
    $events = $eventStore->loadEvents($terminalId);
    foreach ($events as $event) {
        $class = get_class($event);
        $short = substr($class, strrpos($class, '\\') + 1);
        $method = 'on' . $short;
        if (method_exists($readModel, $method)) {
            $readModel->$method($event);
        }
    }
}

// ── State Store ───────────────────────────────────────────────────────────────
use Dranzd\StorebunkPos\Demo\Cli\StateStore;
$stateStore = new StateStore(StateStore::defaultPath());

return [
    'commandBus'        => $commandBus,
    'eventStore'        => $eventStore,
    'terminalRepo'      => $terminalRepository,
    'shiftRepo'         => $shiftRepository,
    'sessionRepo'       => $sessionRepository,
    'terminalReadModel' => $terminalReadModel,
    'orderingService'   => $orderingService,
    'inventoryService'  => $inventoryService,
    'paymentService'    => $paymentService,
    'pendingSyncQueue'  => $pendingSyncQueue,
    'idempotencyReg'    => $idempotencyRegistry,
    'stateStore'        => $stateStore,
];
