<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Terminal\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Terminal\Command\DecommissionTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\DisableTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DecommissionTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DisableTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RegisterTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RenameTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\RenameTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRenamed;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Terminal\Repository\InMemoryTerminalRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class RenameTerminalHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryTerminalRepository $terminalRepository;
    private RenameTerminalHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->terminalRepository = new InMemoryTerminalRepository($this->eventStore);
        $this->handler = new RenameTerminalHandler($this->terminalRepository);
    }

    public function test_renames_a_terminal_and_records_old_and_new_names(): void
    {
        $terminalId = $this->registerTerminal();

        ($this->handler)(new RenameTerminal($terminalId->toNative(), 'POS-01-Front'));

        $renamed = array_values(array_filter(
            $this->eventStore->loadEvents($terminalId->toNative()),
            fn ($e) => $e instanceof TerminalRenamed
        ));

        $this->assertCount(1, $renamed);
        $this->assertSame('POS-01', $renamed[0]->getOldName());
        $this->assertSame('POS-01-Front', $renamed[0]->getNewName());
    }

    public function test_refuses_renaming_to_the_same_name(): void
    {
        $terminalId = $this->registerTerminal();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('New name is the same as the current name');

        ($this->handler)(new RenameTerminal($terminalId->toNative(), 'POS-01'));
    }

    public function test_refuses_renaming_a_decommissioned_terminal(): void
    {
        $terminalId = $this->registerTerminal();
        $disable = new DisableTerminalHandler($this->terminalRepository);
        $disable(new DisableTerminal($terminalId->toNative()));
        $decommission = new DecommissionTerminalHandler($this->terminalRepository);
        $decommission(new DecommissionTerminal($terminalId->toNative(), 'end of life'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot rename a decommissioned terminal');

        ($this->handler)(new RenameTerminal($terminalId->toNative(), 'POS-01-Front'));
    }

    private function registerTerminal(): TerminalId
    {
        $terminalId = new TerminalId();
        $register = new RegisterTerminalHandler($this->terminalRepository);
        $register(new RegisterTerminal($terminalId->toNative(), (new BranchId())->toNative(), 'POS-01'));

        return $terminalId;
    }
}
