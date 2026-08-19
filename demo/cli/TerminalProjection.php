<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Demo\Cli;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\EventStore;
use Dranzd\StorebunkPos\Infrastructure\Terminal\ReadModel\InMemoryTerminalReadModel;

/**
 * Replays one terminal's events into the read model after a command touches
 * it. A simple approach suitable for a demo, not production: it re-reads the
 * whole stream rather than projecting the events just recorded.
 *
 * A class rather than a function in bootstrap.php, which both defines symbols
 * and runs a program — a file should do one or the other.
 */
final class TerminalProjection
{
    public static function project(
        EventStore $eventStore,
        InMemoryTerminalReadModel $readModel,
        string $terminalId
    ): void {
        if (!$eventStore->hasEvents($terminalId)) {
            return;
        }

        foreach ($eventStore->loadEvents($terminalId) as $event) {
            $class  = get_class($event);
            $short  = substr($class, (int) strrpos($class, '\\') + 1);
            $method = 'on' . $short;

            if (method_exists($readModel, $method)) {
                $readModel->$method($event);
            }
        }
    }
}
