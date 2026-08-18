<?php

declare(strict_types=1);

use Dranzd\Common\Cqrs\Infrastructure\Bus\SimpleCommandBus;
use Dranzd\StorebunkPos\Application\PosSession\Command\CancelOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\CompleteOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\DeactivateOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\EndSession;
use Dranzd\StorebunkPos\Application\PosSession\Command\InitiateCheckout;
use Dranzd\StorebunkPos\Application\PosSession\Command\ParkOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\ReactivateOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\RequestPayment;
use Dranzd\StorebunkPos\Application\PosSession\Command\ResumeOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrderOffline;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Application\PosSession\Command\SyncOrderOnline;
use Dranzd\StorebunkPos\Demo\Cli\CliArgs;
use Dranzd\StorebunkPos\Demo\Cli\Output;
use Dranzd\StorebunkPos\Demo\Cli\StateStore;
use Dranzd\StorebunkPos\Demo\Cli\Utils;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use Dranzd\StorebunkPos\Tests\Stub\Service\StubOrderingService;
use Dranzd\StorebunkPos\Tests\Stub\Service\StubPaymentService;

function handleSession(
    SimpleCommandBus $commandBus,
    StateStore $stateStore,
    StubOrderingService $orderingService,
    StubPaymentService $paymentService,
    string $subcommand,
    CliArgs $args
): void {
    switch ($subcommand) {
        case 'start':
            sessionStart($commandBus, $stateStore, $args);
            break;
        case 'new-order':
            sessionNewOrder($commandBus, $stateStore, $args);
            break;
        case 'park':
            sessionPark($commandBus, $stateStore, $args);
            break;
        case 'resume':
            sessionResume($commandBus, $stateStore, $args);
            break;
        case 'deactivate':
            sessionDeactivate($commandBus, $stateStore, $args);
            break;
        case 'reactivate':
            sessionReactivate($commandBus, $stateStore, $args);
            break;
        case 'checkout':
            sessionCheckout($commandBus, $stateStore, $args);
            break;
        case 'pay':
            sessionPay($commandBus, $stateStore, $paymentService, $args);
            break;
        case 'complete':
            sessionComplete($commandBus, $stateStore, $orderingService, $args);
            break;
        case 'cancel':
            sessionCancel($commandBus, $stateStore, $args);
            break;
        case 'end':
            sessionEnd($commandBus, $stateStore, $args);
            break;
        case 'new-order-offline':
            sessionNewOrderOffline($commandBus, $stateStore, $args);
            break;
        case 'sync':
            sessionSync($commandBus, $stateStore, $args);
            break;
        default:
            Output::error("Unknown session subcommand: {$subcommand}");
            Output::blank();
            Output::usage('./demo session <start|new-order|park|resume|deactivate|reactivate|checkout|pay|complete|cancel|end|new-order-offline|sync> [options]');
            exit(1);
    }
}

function sessionStart(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $shiftIdRaw = $args->get('shift-id', $stateStore->get('last_shift_id', ''));
    if ($shiftIdRaw === '') {
        Output::error('--shift-id is required (or run shift open first)');
        exit(1);
    }

    $terminalIdRaw = $args->get('terminal-id', $stateStore->get('last_terminal_id', ''));
    if ($terminalIdRaw === '') {
        Output::error('--terminal-id is required');
        exit(1);
    }

    $cashierIdRaw = $args->get('cashier-id', $stateStore->get('last_cashier_id', ''));
    if ($cashierIdRaw === '') {
        Output::error('--cashier-id is required (or run shift open first)');
        exit(1);
    }

    $sessionId  = new SessionId();
    $shiftId    = new ShiftId($shiftIdRaw);
    $terminalId = new TerminalId($terminalIdRaw);
    $cashierId  = new CashierId($cashierIdRaw);

    try {
        $commandBus->dispatch(new StartSession(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative(),
            $cashierId->toNative()
        ));

        $stateStore->set('last_session_id', $sessionId->toNative());
        $stateStore->push('session_ids', $sessionId->toNative());

        Output::success('POS session started.');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Shift ID', $shiftId->toNative());
        Output::field('Terminal ID', $terminalId->toNative());
        Output::field('Cashier ID', $cashierId->toNative());
        Output::field('Started At', (new DateTimeImmutable())->format(DATE_ATOM));
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionNewOrder(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required (or run session start first)');
        exit(1);
    }

    $sessionId = new SessionId($sessionIdRaw);
    $orderId   = new OrderId();

    try {
        $commandBus->dispatch(new StartNewOrder(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        $stateStore->set('last_order_id', $orderId->toNative());
        $stateStore->push('order_ids', $orderId->toNative());

        Output::success('New order started.');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Order ID', $orderId->toNative());
        Output::field('State', 'Building');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionPark(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $sessionId = new SessionId($sessionIdRaw);

    try {
        $commandBus->dispatch(new ParkOrder($sessionId->toNative()));

        Output::success('Order parked.');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('State', 'Idle (order parked)');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionResume(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $orderIdRaw = $args->get('order-id', $stateStore->get('last_order_id', ''));
    if ($orderIdRaw === '') {
        Output::error('--order-id is required (or park an order first)');
        exit(1);
    }

    $sessionId = new SessionId($sessionIdRaw);
    $orderId   = new OrderId($orderIdRaw);

    try {
        $commandBus->dispatch(new ResumeOrder(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        // The resumed order is now the active one — later steps (checkout,
        // pay, complete) default to last_order_id, so keep it in sync.
        $stateStore->set('last_order_id', $orderId->toNative());

        Output::success('Order resumed.');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Order ID', $orderId->toNative());
        Output::field('State', 'Building');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionDeactivate(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $reason = $args->get('reason', 'Deactivated due to inactivity (TTL expiry)');

    $sessionId = new SessionId($sessionIdRaw);

    try {
        $commandBus->dispatch(new DeactivateOrder(
            $sessionId->toNative(),
            $reason
        ));

        Output::success('Active order deactivated.');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Reason', $reason);
        Output::field('State', 'Idle');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionReactivate(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $orderIdRaw = $args->get('order-id', $stateStore->get('last_order_id', ''));
    if ($orderIdRaw === '') {
        Output::error('--order-id is required');
        exit(1);
    }

    $sessionId = new SessionId($sessionIdRaw);
    $orderId   = new OrderId($orderIdRaw);

    try {
        $commandBus->dispatch(new ReactivateOrder(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        // The reactivated order is now the active one — keep last_order_id
        // in sync for the follow-on checkout/pay/complete defaults.
        $stateStore->set('last_order_id', $orderId->toNative());

        Output::success('Order reactivated (inventory re-reserved).');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Order ID', $orderId->toNative());
        Output::field('State', 'Building');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionCheckout(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $sessionId = new SessionId($sessionIdRaw);

    try {
        $commandBus->dispatch(new InitiateCheckout($sessionId->toNative()));

        Output::success('Checkout initiated (order confirmed, reservation converted to hard).');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('State', 'Checkout');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionPay(
    SimpleCommandBus $commandBus,
    StateStore $stateStore,
    StubPaymentService $paymentService,
    CliArgs $args
): void {
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $amount        = $args->getInt('amount', 0);
    $currency      = $args->get('currency', 'PHP');
    $paymentMethod = $args->get('method', 'cash');

    if ($amount <= 0) {
        Output::error('--amount must be a positive integer (minor units)');
        exit(1);
    }

    $sessionId = new SessionId($sessionIdRaw);
    try {
        $commandBus->dispatch(new RequestPayment(
            $sessionId->toNative(),
            $amount,
            $currency,
            $paymentMethod
        ));

        Output::success('Payment authorized and applied.');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Amount', Output::money($amount, $currency));
        Output::field('Method', $paymentMethod);
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionComplete(
    SimpleCommandBus $commandBus,
    StateStore $stateStore,
    StubOrderingService $orderingService,
    CliArgs $args
): void {
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $orderIdRaw = $args->get('order-id', $stateStore->get('last_order_id', ''));
    if ($orderIdRaw === '') {
        Output::error('--order-id is required');
        exit(1);
    }

    $sessionId = new SessionId($sessionIdRaw);
    $orderId   = new OrderId($orderIdRaw);

    $orderingService->markOrderAsFullyPaid($orderId);

    try {
        $commandBus->dispatch(new CompleteOrder($sessionId->toNative()));

        Output::success('Order completed (inventory deducted).');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Order ID', $orderId->toNative());
        Output::field('State', 'Idle');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionCancel(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $reason = $args->get('reason', 'Cancelled by cashier');

    $sessionId = new SessionId($sessionIdRaw);

    try {
        $commandBus->dispatch(new CancelOrder(
            $sessionId->toNative(),
            $reason
        ));

        Output::success('Order cancelled (reservation released).');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Reason', $reason);
        Output::field('State', 'Idle');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionEnd(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $sessionId = new SessionId($sessionIdRaw);

    try {
        $commandBus->dispatch(new EndSession($sessionId->toNative()));

        Output::success('POS session ended.');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Ended At', (new DateTimeImmutable())->format(DATE_ATOM));
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionNewOrderOffline(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $sessionId = new SessionId($sessionIdRaw);
    $orderId   = new OrderId();

    try {
        $commandBus->dispatch(new StartNewOrderOffline(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        $stateStore->set('last_order_id', $orderId->toNative());
        $stateStore->push('order_ids', $orderId->toNative());
        $stateStore->push('pending_sync_order_ids', $orderId->toNative());

        Output::success('New order started OFFLINE (pending sync).');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Order ID', $orderId->toNative());
        Output::field('State', 'Building (offline)');
        Output::warning('Order is pending sync to ordering BC');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function sessionSync(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $sessionIdRaw = $args->get('session-id', $stateStore->get('last_session_id', ''));
    if ($sessionIdRaw === '') {
        Output::error('--session-id is required');
        exit(1);
    }

    $orderIdRaw = $args->get('order-id', $stateStore->get('last_order_id', ''));
    if ($orderIdRaw === '') {
        Output::error('--order-id is required');
        exit(1);
    }

    $branchIdRaw = $args->get('branch-id', $stateStore->get('last_branch_id', 'branch-001'));

    $sessionId = new SessionId($sessionIdRaw);
    $orderId   = new OrderId($orderIdRaw);
    $branchId  = new BranchId($branchIdRaw);

    try {
        // The demo plays the CONSUMER here: it decides what context the
        // Ordering BC needs; POS just passes the array through (ADR-006).
        $commandBus->dispatch(new SyncOrderOnline(
            $sessionId->toNative(),
            $orderId->toNative(),
            ['branch_id' => $branchId->toNative()]
        ));

        $stateStore->removeFromList('pending_sync_order_ids', $orderId->toNative());

        Output::success('Order synced online (draft created in ordering BC).');
        Output::field('Session ID', $sessionId->toNative());
        Output::field('Order ID', $orderId->toNative());
        Output::field('State', 'Building (online)');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}
