<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Terminal\Handler;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\Terminal\Command\ActivateTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\DecommissionTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\DisableTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\ActivateTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DecommissionTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\DisableTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RecommissionTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\Handler\RegisterTerminalHandler;
use Dranzd\StorebunkPos\Application\Terminal\Command\RecommissionTerminal;
use Dranzd\StorebunkPos\Application\Terminal\Command\RegisterTerminal;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRecommissioned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\Terminal\Repository\InMemoryTerminalRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class RecommissionTerminalHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryTerminalRepository $terminalRepository;
    private RecommissionTerminalHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->terminalRepository = new InMemoryTerminalRepository($this->eventStore);
        $this->handler = new RecommissionTerminalHandler($this->terminalRepository);
    }

    public function test_recommissions_a_decommissioned_terminal(): void
    {
        $terminalId = $this->decommissionedTerminal();

        ($this->handler)(new RecommissionTerminal($terminalId->toNative(), 'back in service'));

        $recommissioned = array_values(array_filter(
            $this->eventStore->loadEvents($terminalId->toNative()),
            fn ($e) => $e instanceof TerminalRecommissioned
        ));

        $this->assertCount(1, $recommissioned);
        $this->assertSame('back in service', $recommissioned[0]->getReason());
    }

    public function test_recommissioned_terminal_lands_in_disabled_and_can_activate(): void
    {
        $terminalId = $this->decommissionedTerminal();
        ($this->handler)(new RecommissionTerminal($terminalId->toNative(), 'back in service'));

        // Recommission lands in Disabled, so activation must succeed again —
        // it would throw if the terminal were still decommissioned.
        $activate = new ActivateTerminalHandler($this->terminalRepository);

        $this->expectNotToPerformAssertions();

        $activate(new ActivateTerminal($terminalId->toNative()));
    }

    public function test_refuses_to_recommission_a_terminal_that_is_not_decommissioned(): void
    {
        $terminalId = new TerminalId();
        $register = new RegisterTerminalHandler($this->terminalRepository);
        $register(new RegisterTerminal($terminalId->toNative(), (new BranchId())->toNative(), 'POS-01'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Terminal is not decommissioned');

        ($this->handler)(new RecommissionTerminal($terminalId->toNative(), 'back in service'));
    }

    private function decommissionedTerminal(): TerminalId
    {
        $terminalId = new TerminalId();
        $register = new RegisterTerminalHandler($this->terminalRepository);
        $register(new RegisterTerminal($terminalId->toNative(), (new BranchId())->toNative(), 'POS-01'));
        $disable = new DisableTerminalHandler($this->terminalRepository);
        $disable(new DisableTerminal($terminalId->toNative()));
        $decommission = new DecommissionTerminalHandler($this->terminalRepository);
        $decommission(new DecommissionTerminal($terminalId->toNative(), 'end of life'));

        return $terminalId;
    }
}
