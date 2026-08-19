<?php

declare(strict_types=1);

use Dranzd\Common\Cqrs\Infrastructure\Bus\SimpleCommandBus;
use Dranzd\Common\Cqrs\Infrastructure\HandlerRegistry\InMemoryHandlerRegistry;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\EventStore;
use Dranzd\StorebunkPos\Demo\Cli\FileEventStore;
use Dranzd\StorebunkPos\Demo\Cli\StateStore;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\NewOrderStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCancelledViaPOS;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCompleted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCreatedOffline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderDeactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderMarkedPendingSync;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderParked;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderReactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderResumed;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderSyncedOnline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionEnded;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftAssigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftForceClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftOpened;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftUnassigned;
use Dranzd\StorebunkPos\Application\PosSession\Command\CancelOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\CompleteOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\DeactivateOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\EndSession;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\CancelOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\DeactivateOrderHandler;
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
use Dranzd\StorebunkPos\Application\Shared\OfflineStateReplay;
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
use Dranzd\StorebunkPos\Application\Terminal\Command\DecommissionTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\DisableTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\ActivateTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DecommissionTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DisableTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\ReassignTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RecommissionTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RegisterTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RenameTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\SetTerminalMaintenanceHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\ReassignTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\RecommissionTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\RenameTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\SetTerminalMaintenance;
use Dranzd\StorebunkPos\Demo\Cli\FileShiftSlotReservation;
use Dranzd\StorebunkPos\Domain\Service\ShiftSlotBook;
use Dranzd\StorebunkPos\Domain\Service\PendingSyncQueue;
use Dranzd\StorebunkPos\Domain\Service\ShiftClosePolicy;
use Dranzd\StorebunkPos\Infrastructure\PosSession\ReadModel\InMemoryPosSessionReadModel;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Infrastructure\Shift\ReadModel\InMemoryShiftReadModel;
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
$shiftReadModel       = new InMemoryShiftReadModel();

// ── BC Service Stubs ──────────────────────────────────────────────────────────
$orderingService   = new StubOrderingService();
$inventoryService  = new StubInventoryService();
$paymentService    = new StubPaymentService();

// ── Domain Services ───────────────────────────────────────────────────────────
$pendingSyncQueue    = new PendingSyncQueue();
$idempotencyRegistry = new IdempotencyRegistry();
$shiftClosePolicy    = new ShiftClosePolicy();
$shiftSlotBook        = new ShiftSlotBook();
$shiftSlotReservation = new FileShiftSlotReservation(
    FileShiftSlotReservation::defaultPath(),
    $shiftSlotBook
);

// ── Rebuild offline-sync state from persisted events ─────────────────────────
// The queue and registry are plain in-memory objects; replay the persisted
// session events so offline orders queued in an earlier process are still
// pending here. The rules live in OfflineStateReplay, where they can be
// tested — getting a registry purpose wrong here is invisible until a
// redelivery is either swallowed or wrongly refused.
$persistedEvents = $eventStore->allEvents();
foreach ($persistedEvents as $aggregateEvents) {
    OfflineStateReplay::rebuild($aggregateEvents, $pendingSyncQueue, $idempotencyRegistry);
}

foreach ($persistedEvents as $aggregateEvents) {
    foreach ($aggregateEvents as $event) {
        // Project the session read model too — CloseShiftHandler's
        // active-session guard reads it, and an unprojected (empty) model
        // would let a shift close while sessions are still running.
        match (true) {
            $event instanceof SessionStarted        => $posSessionReadModel->onSessionStarted($event),
            $event instanceof NewOrderStarted       => $posSessionReadModel->onNewOrderStarted($event),
            $event instanceof OrderParked           => $posSessionReadModel->onOrderParked($event),
            $event instanceof OrderResumed          => $posSessionReadModel->onOrderResumed($event),
            $event instanceof OrderCompleted        => $posSessionReadModel->onOrderCompleted($event),
            $event instanceof OrderCancelledViaPOS  => $posSessionReadModel->onOrderCancelledViaPOS($event),
            $event instanceof OrderDeactivated      => $posSessionReadModel->onOrderDeactivated($event),
            $event instanceof OrderReactivated      => $posSessionReadModel->onOrderReactivated($event),
            $event instanceof OrderCreatedOffline   => $posSessionReadModel->onOrderCreatedOffline($event),
            $event instanceof OrderMarkedPendingSync => $posSessionReadModel->onOrderMarkedPendingSync($event),
            $event instanceof OrderSyncedOnline     => $posSessionReadModel->onOrderSyncedOnline($event),
            $event instanceof SessionEnded          => $posSessionReadModel->onSessionEnded($event),
            // Shift read model — query state, and the seed source for the
            // shift-slot reservation file on first run (issues 8002/8003).
            $event instanceof ShiftOpened           => $shiftReadModel->onShiftOpened($event),
            $event instanceof ShiftAssigned         => $shiftReadModel->onShiftAssigned($event),
            $event instanceof ShiftUnassigned       => $shiftReadModel->onShiftUnassigned($event),
            $event instanceof ShiftClosed           => $shiftReadModel->onShiftClosed($event),
            $event instanceof ShiftForceClosed      => $shiftReadModel->onShiftForceClosed($event),
            default                                 => null,
        };
    }
}

// Seed the shift-slot reservation file from replayed events when it does
// not exist yet; once present, the FILE is the live cross-process authority
// (see FileShiftSlotReservation / issue 8003).
// Seeding is a guard too: it decides which terminals and cashiers are
// occupied. Building that from a history whose order is undefined can free
// an occupied terminal — a stream whose LAST event is a close replays as
// "closed" however its middle is ordered. The CLI refuses shift and session
// commands in this state anyway; not seeding keeps that from resting on one
// layer.
if ($eventStore->malformedStreams() === []) {
    $shiftSlotReservation->seedIfMissing(
        FileShiftSlotReservation::openShiftsById($shiftReadModel->getOpenShifts())
    );
}

// ── Command Handlers ──────────────────────────────────────────────────────────
$handlers = [
    // Terminal
    RegisterTerminal::class      => new RegisterTerminalHandler($terminalRepository),
    ActivateTerminal::class      => new ActivateTerminalHandler($terminalRepository),
    DisableTerminal::class       => new DisableTerminalHandler($terminalRepository),
    SetTerminalMaintenance::class => new SetTerminalMaintenanceHandler($terminalRepository),
    RenameTerminal::class        => new RenameTerminalHandler($terminalRepository),
    ReassignTerminal::class      => new ReassignTerminalHandler($terminalRepository),
    DecommissionTerminal::class  => new DecommissionTerminalHandler($terminalRepository),
    RecommissionTerminal::class  => new RecommissionTerminalHandler($terminalRepository),

    // Shift
    OpenShift::class        => new OpenShiftHandler($shiftRepository, $shiftSlotReservation),
    AssignShift::class      => new AssignShiftHandler($shiftRepository, $shiftSlotReservation),
    UnassignShift::class    => new UnassignShiftHandler($shiftRepository, $shiftSlotReservation),
    CloseShift::class       => new CloseShiftHandler($shiftRepository, $shiftClosePolicy, $posSessionReadModel, $shiftSlotReservation),
    ForceCloseShift::class  => new ForceCloseShiftHandler($shiftRepository, $shiftSlotReservation),
    RecordCashDrop::class   => new RecordCashDropHandler($shiftRepository),

    // PosSession (online)
    StartSession::class     => new StartSessionHandler($sessionRepository),
    StartNewOrder::class    => new StartNewOrderHandler($sessionRepository),
    ParkOrder::class        => new ParkOrderHandler($sessionRepository),
    ResumeOrder::class      => new ResumeOrderHandler($sessionRepository),
    DeactivateOrder::class  => new DeactivateOrderHandler($sessionRepository),
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
    'shiftReadModel'    => $shiftReadModel,
    'shiftSlots'        => $shiftSlotReservation,
];
