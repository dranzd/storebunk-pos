<?php

declare(strict_types=1);

use Dranzd\Common\Cqrs\Infrastructure\Bus\SimpleCommandBus;
use Dranzd\StorebunkPos\Application\Shift\Command\CloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\ForceCloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Application\Shift\Command\RecordCashDrop;
use Dranzd\StorebunkPos\Demo\Cli\CliArgs;
use Dranzd\StorebunkPos\Demo\Cli\Output;
use Dranzd\StorebunkPos\Demo\Cli\StateStore;
use Dranzd\StorebunkPos\Demo\Cli\Utils;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

function handleShift(
    SimpleCommandBus $commandBus,
    StateStore $stateStore,
    string $subcommand,
    CliArgs $args
): void {
    switch ($subcommand) {
        case 'open':
            shiftOpen($commandBus, $stateStore, $args);
            break;
        case 'close':
            shiftClose($commandBus, $stateStore, $args);
            break;
        case 'force-close':
            shiftForceClose($commandBus, $stateStore, $args);
            break;
        case 'cash-drop':
            shiftCashDrop($commandBus, $stateStore, $args);
            break;
        default:
            Output::error("Unknown shift subcommand: {$subcommand}");
            Output::blank();
            Output::usage('./demo shift <open|close|force-close|cash-drop> [options]');
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
    $money      = Utils::money($openingCash, $currency);

    try {
        $commandBus->dispatch(new OpenShift($shiftId, $terminalId, $branchId, $cashierId, $money));

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
    $money   = Utils::money($declaredCash, $currency);

    try {
        $commandBus->dispatch(new CloseShift($shiftId, $money));

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
        $commandBus->dispatch(new ForceCloseShift($shiftId, $supervisorId, $reason));

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

function shiftCashDrop(SimpleCommandBus $commandBus, StateStore $stateStore, CliArgs $args): void
{
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
    $money   = Utils::money($amount, $currency);

    try {
        $commandBus->dispatch(new RecordCashDrop($shiftId, $money));

        Output::success('Cash drop recorded.');
        Output::field('Shift ID', $shiftId->toNative());
        Output::field('Amount', Output::money($amount, $currency));
        Output::field('Recorded At', (new DateTimeImmutable())->format(DATE_ATOM));
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}
