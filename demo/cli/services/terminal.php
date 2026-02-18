<?php

declare(strict_types=1);

use Dranzd\Common\Cqrs\Infrastructure\Bus\SimpleCommandBus;
use Dranzd\StorebunkPos\Application\Terminal\Command\ActivateTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\DisableTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\SetTerminalMaintenance;
use Dranzd\StorebunkPos\Application\Terminal\ReadModel\TerminalReadModelInterface;
use Dranzd\StorebunkPos\Demo\Cli\CliArgs;
use Dranzd\StorebunkPos\Demo\Cli\Output;
use Dranzd\StorebunkPos\Demo\Cli\StateStore;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Terminal\ReadModel\InMemoryTerminalReadModel;
use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

/**
 * @param SimpleCommandBus         $commandBus
 * @param InMemoryTerminalReadModel $terminalReadModel
 * @param StateStore               $stateStore
 * @param string                   $subcommand
 * @param CliArgs                  $args
 */
function handleTerminal(
    SimpleCommandBus $commandBus,
    InMemoryTerminalReadModel $terminalReadModel,
    StateStore $stateStore,
    string $subcommand,
    CliArgs $args
): void {
    switch ($subcommand) {
        case 'register':
            terminalRegister($commandBus, $terminalReadModel, $stateStore, $args);
            break;
        case 'activate':
            terminalActivate($commandBus, $terminalReadModel, $stateStore, $args);
            break;
        case 'disable':
            terminalDisable($commandBus, $terminalReadModel, $stateStore, $args);
            break;
        case 'maintenance':
            terminalMaintenance($commandBus, $terminalReadModel, $stateStore, $args);
            break;
        case 'get':
            terminalGet($terminalReadModel, $stateStore, $args);
            break;
        case 'list':
            terminalList($terminalReadModel, $args);
            break;
        default:
            Output::error("Unknown terminal subcommand: {$subcommand}");
            Output::blank();
            Output::usage('./demo terminal <register|activate|disable|maintenance|get|list> [options]');
            exit(1);
    }
}

function terminalRegister(
    SimpleCommandBus $commandBus,
    InMemoryTerminalReadModel $terminalReadModel,
    StateStore $stateStore,
    CliArgs $args
): void {
    $name     = $args->require('name');
    $branchRaw = $args->get('branch-id');
    $branchId = $branchRaw !== '' ? new BranchId($branchRaw) : new BranchId();
    $terminalId = new TerminalId();

    try {
        $command = new RegisterTerminal($terminalId, $branchId, $name);
        $commandBus->dispatch($command);

        // Project into read model
        global $eventStore;
        projectTerminalReadModel($eventStore, $terminalReadModel, $terminalId->toNative());

        // Persist to state store
        $stateStore->set('last_terminal_id', $terminalId->toNative());
        $stateStore->set('last_branch_id', $branchId->toNative());
        $stateStore->push('terminal_ids', $terminalId->toNative());

        Output::success('Terminal registered successfully.');
        Output::field('Terminal ID', $terminalId->toNative());
        Output::field('Branch ID', $branchId->toNative());
        Output::field('Name', $name);
        Output::field('Status', 'active');
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function terminalActivate(
    SimpleCommandBus $commandBus,
    InMemoryTerminalReadModel $terminalReadModel,
    StateStore $stateStore,
    CliArgs $args
): void {
    $terminalIdRaw = $args->get('terminal-id', $stateStore->get('last_terminal_id', ''));
    if ($terminalIdRaw === '') {
        Output::error('--terminal-id is required (or run register first)');
        exit(1);
    }
    $terminalId = new TerminalId($terminalIdRaw);

    try {
        $commandBus->dispatch(new ActivateTerminal($terminalId));
        global $eventStore;
        projectTerminalReadModel($eventStore, $terminalReadModel, $terminalId->toNative());

        Output::success('Terminal activated.');
        Output::field('Terminal ID', $terminalId->toNative());
        Output::field('Status', 'active');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function terminalDisable(
    SimpleCommandBus $commandBus,
    InMemoryTerminalReadModel $terminalReadModel,
    StateStore $stateStore,
    CliArgs $args
): void {
    $terminalIdRaw = $args->get('terminal-id', $stateStore->get('last_terminal_id', ''));
    if ($terminalIdRaw === '') {
        Output::error('--terminal-id is required');
        exit(1);
    }
    $terminalId = new TerminalId($terminalIdRaw);

    try {
        $commandBus->dispatch(new DisableTerminal($terminalId));
        global $eventStore;
        projectTerminalReadModel($eventStore, $terminalReadModel, $terminalId->toNative());

        Output::success('Terminal disabled.');
        Output::field('Terminal ID', $terminalId->toNative());
        Output::field('Status', 'disabled');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function terminalMaintenance(
    SimpleCommandBus $commandBus,
    InMemoryTerminalReadModel $terminalReadModel,
    StateStore $stateStore,
    CliArgs $args
): void {
    $terminalIdRaw = $args->get('terminal-id', $stateStore->get('last_terminal_id', ''));
    if ($terminalIdRaw === '') {
        Output::error('--terminal-id is required');
        exit(1);
    }
    $terminalId = new TerminalId($terminalIdRaw);

    try {
        $commandBus->dispatch(new SetTerminalMaintenance($terminalId));
        global $eventStore;
        projectTerminalReadModel($eventStore, $terminalReadModel, $terminalId->toNative());

        Output::success('Terminal set to maintenance.');
        Output::field('Terminal ID', $terminalId->toNative());
        Output::field('Status', 'maintenance');
    } catch (AggregateNotFoundException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    } catch (InvariantViolationException $e) {
        Output::domainError($e->getMessage());
        exit(1);
    }
}

function terminalGet(
    InMemoryTerminalReadModel $terminalReadModel,
    StateStore $stateStore,
    CliArgs $args
): void {
    $terminalIdRaw = $args->get('terminal-id', $stateStore->get('last_terminal_id', ''));
    if ($terminalIdRaw === '') {
        Output::error('--terminal-id is required (or run register first)');
        exit(1);
    }

    $terminal = $terminalReadModel->getTerminal($terminalIdRaw);
    if ($terminal === null) {
        Output::error("Terminal not found: {$terminalIdRaw}");
        exit(1);
    }

    Output::section('Terminal Details');
    Output::field('Terminal ID', $terminal['terminal_id']);
    Output::field('Branch ID', $terminal['branch_id']);
    Output::field('Name', $terminal['name']);
    Output::field('Status', $terminal['status']);
    Output::field('Registered At', $terminal['registered_at'] ?? 'n/a');
}

function terminalList(
    InMemoryTerminalReadModel $terminalReadModel,
    CliArgs $args
): void {
    $branchFilter = $args->get('branch-id');
    $statusFilter = $args->get('status');

    if ($branchFilter !== '') {
        $terminals = $terminalReadModel->getTerminalsByBranch($branchFilter);
    } elseif ($statusFilter !== '') {
        $terminals = $terminalReadModel->getTerminalsByStatus($statusFilter);
    } else {
        $terminals = $terminalReadModel->getAllTerminals();
    }

    if (empty($terminals)) {
        Output::info('No terminals found.');
        return;
    }

    Output::section('Terminals (' . count($terminals) . ')');
    foreach ($terminals as $terminal) {
        Output::separator();
        Output::field('Terminal ID', $terminal['terminal_id']);
        Output::field('Branch ID', $terminal['branch_id']);
        Output::field('Name', $terminal['name']);
        Output::field('Status', $terminal['status']);
    }
    Output::separator();
}
