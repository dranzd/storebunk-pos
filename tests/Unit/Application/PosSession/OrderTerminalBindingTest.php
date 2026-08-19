<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ParkOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ReactivateOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ResumeOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\ParkOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\ReactivateOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\ResumeOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use Dranzd\StorebunkPos\Tests\Stub\Service\StubInventoryService;
use PHPUnit\Framework\TestCase;

/**
 * Invariant: an order is only ever handled from the terminal it belongs to
 * (issue 8002).
 *
 * Two legs hold it up, and both are pinned here:
 *
 * 1. A session is bound to one terminal when it starts, and nothing moves it.
 * 2. An order already held by a session cannot be REACHED from another one:
 *    resume, reactivate and sync each check the id against that session's own
 *    parked / inactive / pending-sync list, and complete/cancel take no id at
 *    all — they act on the session's own active order.
 *
 * What this does NOT cover, deliberately: CLAIMING an id. `StartNewOrder`
 * takes an id from the caller, so a session can only be stopped from reusing
 * one it has already started (asserted below). Two different sessions being
 * handed the same id is a host concern — order ids belong to the Ordering
 * context, and a host that lets a caller supply one should check it against
 * the caller's terminal, which is what
 * MultiTerminalEnforcementService::assertOrderBelongsToTerminal() is for.
 *
 * The point of pinning this is that nobody later "fixes" it by adding a
 * lookup table: a read model that can disagree with the aggregate is what
 * issue 8003 spent five review rounds proving cannot be a source of truth.
 */
final class OrderTerminalBindingTest extends TestCase
{
    private InMemoryPosSessionRepository $sessionRepository;
    private SessionId $sessionOnTerminalA;
    private SessionId $sessionOnTerminalB;

    protected function setUp(): void
    {
        $this->sessionRepository = new InMemoryPosSessionRepository(new InMemoryEventStore());
        $this->sessionOnTerminalA = $this->startSession();
        $this->sessionOnTerminalB = $this->startSession();
    }

    public function test_another_terminal_cannot_resume_a_parked_order(): void
    {
        $orderId = $this->startAndParkOrder($this->sessionOnTerminalA);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Order is not in parked list');

        (new ResumeOrderHandler($this->sessionRepository))(
            new ResumeOrder($this->sessionOnTerminalB->toNative(), $orderId->toNative())
        );
    }

    public function test_another_terminal_cannot_reactivate_an_expired_order(): void
    {
        $orderId = $this->startOrder($this->sessionOnTerminalA);
        $session = $this->sessionRepository->load($this->sessionOnTerminalA);
        $session->deactivateOrder('ttl expiry');
        $this->sessionRepository->store($session);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Order is not in inactive list');

        (new ReactivateOrderHandler($this->sessionRepository, new StubInventoryService()))(
            new ReactivateOrder($this->sessionOnTerminalB->toNative(), $orderId->toNative())
        );
    }

    public function test_an_id_less_command_touches_only_its_own_terminals_order(): void
    {
        // Park, like complete and cancel, takes no order id: it acts on the
        // session's own active order. With BOTH sessions holding one, parking
        // on B must park B's and leave A's exactly where it was.
        $orderOnA = $this->startOrder($this->sessionOnTerminalA);
        $orderOnB = $this->startOrder($this->sessionOnTerminalB);

        (new ParkOrderHandler($this->sessionRepository))(
            new ParkOrder($this->sessionOnTerminalB->toNative())
        );

        $this->assertNull(
            $this->sessionRepository->load($this->sessionOnTerminalB)->activeOrderId(),
            "B's order was parked"
        );
        $this->assertTrue(
            $this->sessionRepository->load($this->sessionOnTerminalA)->activeOrderId()?->sameValueAs($orderOnA) ?? false,
            "A's order is untouched"
        );
        $this->assertNotSame($orderOnA->toNative(), $orderOnB->toNative());
    }

    public function test_a_session_stays_on_the_terminal_it_started_on(): void
    {
        // The first leg of the guarantee: if a session could move terminals,
        // everything the order checks buy us would be worthless.
        $orderId = $this->startAndParkOrder($this->sessionOnTerminalA);
        $terminalAtStart = $this->sessionRepository->load($this->sessionOnTerminalA)->terminalId();

        (new ResumeOrderHandler($this->sessionRepository))(
            new ResumeOrder($this->sessionOnTerminalA->toNative(), $orderId->toNative())
        );

        $this->assertTrue(
            $this->sessionRepository->load($this->sessionOnTerminalA)->terminalId()->sameValueAs($terminalAtStart)
        );
    }

    public function test_a_session_cannot_reuse_an_order_id_it_already_started(): void
    {
        // The id is the one thing about a new order the session cannot take
        // on trust: reusing it would put two orders behind one identifier.
        $orderId = $this->startAndParkOrder($this->sessionOnTerminalA);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Order id has already been used in this session');

        (new StartNewOrderHandler($this->sessionRepository))(
            new StartNewOrder($this->sessionOnTerminalA->toNative(), $orderId->toNative())
        );
    }

    public function test_the_owning_terminal_still_works(): void
    {
        // The guarantee must not be a blanket refusal: the session that holds
        // the order resumes it fine.
        $orderId = $this->startAndParkOrder($this->sessionOnTerminalA);

        (new ResumeOrderHandler($this->sessionRepository))(
            new ResumeOrder($this->sessionOnTerminalA->toNative(), $orderId->toNative())
        );

        $this->assertTrue(
            $this->sessionRepository->load($this->sessionOnTerminalA)->activeOrderId()?->sameValueAs($orderId) ?? false
        );
    }

    private function startSession(): SessionId
    {
        $sessionId = new SessionId();
        (new StartSessionHandler($this->sessionRepository))(new StartSession(
            $sessionId->toNative(),
            (new ShiftId())->toNative(),
            (new TerminalId())->toNative(),
            CashierId::generateAsString()
        ));

        return $sessionId;
    }

    private function startOrder(SessionId $sessionId): OrderId
    {
        $orderId = new OrderId();
        (new StartNewOrderHandler($this->sessionRepository))(
            new StartNewOrder($sessionId->toNative(), $orderId->toNative())
        );

        return $orderId;
    }

    private function startAndParkOrder(SessionId $sessionId): OrderId
    {
        $orderId = $this->startOrder($sessionId);
        (new ParkOrderHandler($this->sessionRepository))(new ParkOrder($sessionId->toNative()));

        return $orderId;
    }
}
