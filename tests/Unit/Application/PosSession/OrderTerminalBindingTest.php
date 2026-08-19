<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession;

use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\CompleteOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ParkOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ReactivateOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ResumeOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\CompleteOrder;
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
use Dranzd\StorebunkPos\Tests\Stub\Service\StubOrderingService;
use PHPUnit\Framework\TestCase;

/**
 * Invariant: an order is only ever handled from the terminal it belongs to
 * (issue 8002).
 *
 * There is no order→terminal lookup anywhere, and there does not need to be:
 * a session is bound to one terminal when it starts, and every command that
 * names an order checks it against THAT session's own lists — parked,
 * inactive, pending-sync — or acts on the session's active order without
 * taking an id at all. So an order can only be reached through the session
 * that holds it, and that session is on one terminal.
 *
 * These tests pin that structural guarantee, so nobody later "fixes" it by
 * adding a second home for the rule — a read model that could disagree with
 * the aggregate is exactly what issue 8003 spent five review rounds proving
 * cannot be a source of truth.
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

    public function test_another_terminal_cannot_complete_an_order_it_does_not_hold(): void
    {
        // Completion takes no order id at all — it acts on the session's own
        // active order, so there is no id for another terminal to pass.
        $this->startOrder($this->sessionOnTerminalA);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('No active order to complete');

        (new CompleteOrderHandler(
            $this->sessionRepository,
            new StubOrderingService(),
            new StubInventoryService()
        ))(new CompleteOrder($this->sessionOnTerminalB->toNative()));
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
