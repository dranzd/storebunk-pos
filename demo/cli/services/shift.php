<?php

declare(strict_types=1);

use Dranzd\Common\Cqrs\Infrastructure\Bus\SimpleCommandBus;
use Dranzd\StorebunkPos\Application\Shift\Command\AssignShift;
use Dranzd\StorebunkPos\Application\Shift\Command\CloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\ForceCloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\UnassignShift;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Application\Shift\Command\RecordCashDrop;
use Dranzd\StorebunkPos\Application\Shift\ReadModel\ShiftReadModelInterface;
use Dranzd\StorebunkPos\Demo\Cli\CliArgs;
use Dranzd\StorebunkPos\Demo\Cli\FileEventStore;
use Dranzd\StorebunkPos\Demo\Cli\FileShiftSlotReservation;
use Dranzd\StorebunkPos\Demo\Cli\Output;
use Dranzd\StorebunkPos\Demo\Cli\StateStore;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;
use Dranzd\Common\Cqrs\Application\Command\Exception\ExecutionFailedException;
use Dranzd\StorebunkPos\Shared\Exception\ConcurrencyException;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

function handleShift(
    SimpleCommandBus $commandBus,
    StateStore $stateStore,
    ShiftReadModelInterface $shiftReadModel,
    FileShiftSlotReservation $shiftSlots,
    FileEventStore $eventStore,
    string $subcommand,
    CliArgs $args
): void {
    switch ($subcommand) {
        case 'open':
            shiftOpen($commandBus, $stateStore, $args);
            break;
        case 'assign':
            shiftAssign($commandBus, $stateStore, $args);
            break;
        case 'unassign':
            shiftUnassign($commandBus, $stateStore, $shiftReadModel, $args);
            break;
        case 'close':
            shiftClose($commandBus, $stateStore, $args);
            break;
        case 'force-close':
            shiftForceClose($commandBus, $stateStore, $args);
            break;
        case 'cash-drop':
            shiftCashDrop($commandBus, $stateStore, $eventStore, $args);
            break;
        case 'reconcile':
            shiftReconcile($shiftReadModel, $shiftSlots, $eventStore);
            break;
        default:
            Output::error("Unknown shift subcommand: {$subcommand}");
            Output::blank();
            Output::usage('./demo shift <open|assign|unassign|close|force-close|cash-drop|reconcile> [options]');
            exit(1);
    }
}

function shiftOpen(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $terminalIdRaw = $args->get('terminal-id', $stateStore->get('last_terminal_id', ''));
    if ($terminalIdRaw === '') {
        Output::error('--terminal-id is required');
        exit(1);
    }

    $branchIdRaw = $args->get('branch-id', $stateStore->get('last_branch_id', ''));
    if ($branchIdRaw === '') {
        Output::error('--branch-id is required');
        exit(1);
    }

    $cashierIdRaw  = $args->get('cashier-id');
    $openingCash   = $args->getInt('opening-cash', 0);
    $currency      = $args->get('currency', 'PHP');

    $shiftId    = new ShiftId();
    $terminalId = new TerminalId($terminalIdRaw);
    $branchId   = new BranchId($branchIdRaw);
    $cashierId  = $cashierIdRaw !== '' ? new CashierId($cashierIdRaw) : new CashierId();

    try {
        $commandBus->dispatch(new OpenShift(
            $shiftId->toNative(),
            $terminalId->toNative(),
            $branchId->toNative(),
            $cashierId->toNative(),
            $openingCash,
            $currency
        ));

        $stateStore->set('last_shift_id', $shiftId->toNative());
        $stateStore->set('last_cashier_id', $cashierId->toNative());
        $stateStore->push('shift_ids', $shiftId->toNative());

        Output::success('Shift opened successfully.');
        Output::field('Shift ID', $shiftId->toNative());
        Output::field('Terminal ID', $terminalId->toNative());
        Output::field('Cashier ID', $cashierId->toNative());
        Output::field('Opening Cash', Output::money($openingCash, $currency));
        Output::field('Opened At', (new DateTimeImmutable())->format(DATE_ATOM));
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function shiftAssign(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $shiftIdRaw = $args->get('shift-id', $stateStore->get('last_shift_id', ''));
    if ($shiftIdRaw === '') {
        Output::error('--shift-id is required (or run shift open first)');
        exit(1);
    }

    $assigneeIdRaw = $args->get('assignee-id', $stateStore->get('last_cashier_id', ''));
    if ($assigneeIdRaw === '') {
        Output::error('--assignee-id is required (or run shift open first)');
        exit(1);
    }

    // Optional comma-separated fallback cashier ids, e.g. --fallback-ids=<uuid>,<uuid>
    $fallbackRaw = $args->get('fallback-ids', '');
    $fallbackIds = array_values(array_filter(array_map('trim', explode(',', $fallbackRaw))));

    $shiftId  = new ShiftId($shiftIdRaw);
    $assignee = new CashierId($assigneeIdRaw);
    $fallbacks = array_map(static fn (string $id) => new CashierId($id), $fallbackIds);

    try {
        $commandBus->dispatch(new AssignShift(
            $shiftId->toNative(),
            $assignee->toNative(),
            array_map(static fn (CashierId $c) => $c->toNative(), $fallbacks)
        ));

        // The shift's operator moved, so the "last cashier" the other
        // subcommands default to has to move with it — otherwise a bare
        // `shift assign` or `session start` keeps aiming at whoever opened
        // the shift, long after they handed it over. Only for the shift those
        // defaults point at, though: `last_cashier_id` is read together with
        // `last_shift_id`, so writing it for a different shift would start a
        // session on one shift under another shift's cashier.
        if ($shiftIdRaw === $stateStore->get('last_shift_id', '')) {
            $stateStore->set('last_cashier_id', $assignee->toNative());
        }

        Output::success('Shift assigned successfully.');
        Output::field('Shift ID', $shiftId->toNative());
        Output::field('Assignee', $assignee->toNative());
        Output::field('Fallbacks', $fallbackIds === [] ? '(none)' : implode(', ', $fallbackIds));
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function shiftUnassign(
    SimpleCommandBus $commandBus,
    StateStore $stateStore,
    ShiftReadModelInterface $shiftReadModel,
    CliArgs $args
): void {
    $shiftIdRaw = $args->get('shift-id', $stateStore->get('last_shift_id', ''));
    if ($shiftIdRaw === '') {
        Output::error('--shift-id is required (or run shift open first)');
        exit(1);
    }

    $shiftId = new ShiftId($shiftIdRaw);

    try {
        $commandBus->dispatch(new UnassignShift($shiftId->toNative()));

        // Operation went back to the opener; the defaults follow it — again
        // only when they point at this shift.
        $shift = $shiftReadModel->getShift($shiftId->toNative());
        if ($shift !== null && $shiftIdRaw === $stateStore->get('last_shift_id', '')) {
            $stateStore->set('last_cashier_id', (string) $shift['opened_by']);
        }

        Output::success('Shift unassigned (now open).');
        Output::field('Shift ID', $shiftId->toNative());
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function shiftClose(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $shiftIdRaw = $args->get('shift-id', $stateStore->get('last_shift_id', ''));
    if ($shiftIdRaw === '') {
        Output::error('--shift-id is required (or run shift open first)');
        exit(1);
    }

    $declaredCash = $args->getInt('declared-cash', 0);
    $currency     = $args->get('currency', 'PHP');

    $shiftId = new ShiftId($shiftIdRaw);

    try {
        $commandBus->dispatch(new CloseShift(
            $shiftId->toNative(),
            $declaredCash,
            $currency
        ));

        Output::success('Shift closed successfully.');
        Output::field('Shift ID', $shiftId->toNative());
        Output::field('Declared Cash', Output::money($declaredCash, $currency));
        Output::field('Closed At', (new DateTimeImmutable())->format(DATE_ATOM));
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function shiftForceClose(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
    $shiftIdRaw    = $args->get('shift-id', $stateStore->get('last_shift_id', ''));
    if ($shiftIdRaw === '') {
        Output::error('--shift-id is required');
        exit(1);
    }

    $supervisorId = $args->get('supervisor-id', 'supervisor-001');
    $reason       = $args->get('reason', 'Force close by supervisor');

    $shiftId = new ShiftId($shiftIdRaw);

    try {
        $commandBus->dispatch(new ForceCloseShift(
            $shiftId->toNative(),
            $supervisorId,
            $reason
        ));

        Output::success('Shift force-closed.');
        Output::field('Shift ID', $shiftId->toNative());
        Output::field('Supervisor', $supervisorId);
        Output::field('Reason', $reason);
        Output::field('Closed At', (new DateTimeImmutable())->format(DATE_ATOM));
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function shiftCashDrop(
    SimpleCommandBus $commandBus,
    StateStore $stateStore,
    FileEventStore $eventStore,
    CliArgs $args
): void {
    $shiftIdRaw = $args->get('shift-id', $stateStore->get('last_shift_id', ''));
    if ($shiftIdRaw === '') {
        Output::error('--shift-id is required');
        exit(1);
    }

    $amount   = $args->getInt('amount', 0);
    $currency = $args->get('currency', 'PHP');

    if ($amount <= 0) {
        Output::error('--amount must be a positive integer (minor units, e.g. 5000 = PHP 50.00)');
        exit(1);
    }

    $shiftId = new ShiftId($shiftIdRaw);

    try {
        // A cash drop is real money leaving the drawer, so losing the race
        // must not mean losing the record. A conflict means another command
        // wrote while we held a stale view: re-read the history and try
        // again, rather than handing the operator an error for something
        // they cannot act on.
        dispatchRetryingOnConflict(
            $eventStore,
            static fn () => $commandBus->dispatch(new RecordCashDrop(
                $shiftId->toNative(),
                $amount,
                $currency
            ))
        );

        Output::success('Cash drop recorded.');
        Output::field('Shift ID', $shiftId->toNative());
        Output::field('Amount', Output::money($amount, $currency));
        Output::field('Recorded At', (new DateTimeImmutable())->format(DATE_ATOM));
    } catch (ExecutionFailedException $e) {
        if (!$e->getPrevious() instanceof ConcurrencyException) {
            throw $e;
        }
        // Out of retries. The operator needs to know the money was NOT
        // recorded and that trying again is the right move — a bare version
        // conflict tells them neither.
        Output::error(
            'The cash drop was NOT recorded: the shift was being changed by other commands at the same time. Try again.'
        );
        Output::info($e->getPrevious()->getMessage());
        exit(1);
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

/**
 * Recovery command for uncertain slot state: rebuild the shift-slot file from
 * the shifts the event store says are open. Needed when a command died
 * between persisting a shift and updating its slots (the CLI reports that as
 * a "slot state is uncertain" error), which would otherwise leave a terminal
 * or a cashier blocked by a shift that is not open.
 *
 * It discards in-flight claims, so it must not run while another demo
 * command is executing.
 */
function shiftReconcile(
    ShiftReadModelInterface $shiftReadModel,
    FileShiftSlotReservation $shiftSlots,
    FileEventStore $eventStore
): void {
    // Rebuilding slots from a history that cannot be ordered would report a
    // confident "corrected N entries" while claiming — or freeing — a
    // terminal on the strength of a shift nobody can operate. The CLI's
    // service gate already refuses before reaching here; this stays as the
    // guarantee for any caller that invokes the function directly. Not dead
    // code — deliberate defence in depth.
    $malformed = $eventStore->malformedStreams();
    if ($malformed !== []) {
        Output::error('Cannot reconcile: some histories cannot be ordered.');
        foreach ($malformed as $reason) {
            Output::info($reason);
        }
        exit(1);
    }

    $openShifts  = FileShiftSlotReservation::openShiftsById($shiftReadModel->getOpenShifts());
    $corrections = $shiftSlots->reconcile($openShifts);

    Output::section('Shift Slot Reconciliation');
    if ($corrections === 0) {
        Output::success('Slots already matched the open shifts; nothing to correct.');
    } else {
        Output::success(sprintf('Corrected %d slot entr%s.', $corrections, $corrections === 1 ? 'y' : 'ies'));
    }
    Output::info(sprintf('Open shifts holding slots: %d', count($openShifts)));
}

/**
 * Run a command, retrying it against a freshly read history when it loses an
 * optimistic-concurrency race.
 *
 * Only safe for commands that can be re-decided from current state — a cash
 * drop is additive, so replaying it after the winner's write is exactly what
 * should happen. A command whose decision the winner may have invalidated
 * (closing a shift, handing it to another cashier) must NOT retry blindly:
 * it has to be re-issued by whoever decided it.
 *
 * @param callable(): void $dispatch
 */
function dispatchRetryingOnConflict(FileEventStore $eventStore, callable $dispatch, int $attempts = 3): void
{
    for ($attempt = 1;; $attempt++) {
        try {
            $dispatch();

            return;
        } catch (Throwable $failure) {
            $conflict = $failure instanceof ExecutionFailedException ? $failure->getPrevious() : $failure;
            if (!$conflict instanceof ConcurrencyException || $attempt >= $attempts) {
                throw $failure;
            }

            // Another process wrote while we held a stale view of the
            // history; the retry has to see what actually landed. The short
            // random wait matters: without it every loser reloads in lockstep
            // and collides again at the same version.
            usleep(random_int(1_000, 15_000));
            $eventStore->reload();
        }
    }
}
