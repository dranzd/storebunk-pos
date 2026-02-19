<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shift\Handler;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Application\Shift\Command\CloseShift;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\CloseShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\Handler\OpenShiftHandler;
use Dranzd\StorebunkPos\Application\Shift\Command\OpenShift;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\ShiftClosePolicy;
use Dranzd\StorebunkPos\Infrastructure\PosSession\ReadModel\InMemoryPosSessionReadModel;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Infrastructure\Shift\Repository\InMemoryShiftRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class CloseShiftHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryShiftRepository $shiftRepository;
    private InMemoryPosSessionRepository $sessionRepository;
    private InMemoryPosSessionReadModel $readModel;
    private CloseShiftHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->shiftRepository = new InMemoryShiftRepository($this->eventStore);
        $this->sessionRepository = new InMemoryPosSessionRepository($this->eventStore);
        $this->readModel = new InMemoryPosSessionReadModel();
        $this->handler = new CloseShiftHandler(
            $this->shiftRepository,
            new ShiftClosePolicy(),
            $this->readModel
        );
    }

    public function test_closes_shift_when_no_active_sessions(): void
    {
        $shiftId = $this->openShift();

        $this->expectNotToPerformAssertions();

        ($this->handler)(new CloseShift(
            $shiftId,
            Money::fromArray(['amount' => 50000, 'currency' => 'PHP'])
        ));
    }

    public function test_blocks_close_when_active_session_exists(): void
    {
        $shiftId = $this->openShift();
        $this->startSession($shiftId);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot close shift');

        ($this->handler)(new CloseShift(
            $shiftId,
            Money::fromArray(['amount' => 50000, 'currency' => 'PHP'])
        ));
    }

    public function test_blocks_close_when_multiple_active_sessions_exist(): void
    {
        $shiftId = $this->openShift();
        $this->startSession($shiftId);
        $this->startSession($shiftId);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('2 active POS session(s)');

        ($this->handler)(new CloseShift(
            $shiftId,
            Money::fromArray(['amount' => 50000, 'currency' => 'PHP'])
        ));
    }

    public function test_allows_close_after_all_sessions_ended(): void
    {
        $shiftId = $this->openShift();
        $sessionId = $this->startSession($shiftId);

        $this->readModel->onSessionEnded(
            \Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionEnded::occur(
                $sessionId,
                new \DateTimeImmutable()
            )
        );

        $this->expectNotToPerformAssertions();

        ($this->handler)(new CloseShift(
            $shiftId,
            Money::fromArray(['amount' => 50000, 'currency' => 'PHP'])
        ));
    }

    public function test_only_blocks_for_sessions_belonging_to_this_shift(): void
    {
        $shiftId = $this->openShift();

        $otherShiftId = new ShiftId();
        $otherSessionId = new SessionId();
        $this->readModel->onSessionStarted(
            \Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted::occur(
                $otherSessionId,
                $otherShiftId,
                new TerminalId(),
                new \DateTimeImmutable()
            )
        );

        $this->expectNotToPerformAssertions();

        ($this->handler)(new CloseShift(
            $shiftId,
            Money::fromArray(['amount' => 50000, 'currency' => 'PHP'])
        ));
    }

    private function openShift(): ShiftId
    {
        $shiftId = new ShiftId();
        $openHandler = new OpenShiftHandler($this->shiftRepository);
        $openHandler(new OpenShift(
            $shiftId,
            new TerminalId(),
            new BranchId(),
            new CashierId(),
            Money::fromArray(['amount' => 50000, 'currency' => 'PHP'])
        ));

        return $shiftId;
    }

    private function startSession(ShiftId $shiftId): SessionId
    {
        $sessionId = new SessionId();
        $terminalId = new TerminalId();

        $startHandler = new StartSessionHandler($this->sessionRepository);
        $startHandler(new StartSession($sessionId, $shiftId, $terminalId));

        $this->readModel->onSessionStarted(
            \Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted::occur(
                $sessionId,
                $shiftId,
                $terminalId,
                new \DateTimeImmutable()
            )
        );

        return $sessionId;
    }
}
