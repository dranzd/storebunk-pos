<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\PosSession\Handler;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\RequestPaymentHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\RequestPayment;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\PaymentRequested;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\PaymentServiceInterface;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class RequestPaymentHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryPosSessionRepository $sessionRepository;
    private PaymentServiceInterface $paymentService;
    private RequestPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore        = new InMemoryEventStore();
        $this->sessionRepository = new InMemoryPosSessionRepository($this->eventStore);
        $this->paymentService    = $this->createMock(PaymentServiceInterface::class);
        $this->handler           = new RequestPaymentHandler(
            $this->sessionRepository,
            $this->paymentService
        );
    }

    public function test_requests_payment_and_applies_when_authorized(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->buildCheckoutSession($sessionId, $orderId);

        $this->paymentService
            ->method('requestPaymentAuthorization')
            ->willReturn(true);

        $this->paymentService
            ->expects($this->once())
            ->method('applyPayment')
            ->with(
                $this->callback(fn (OrderId $id) => $id->toNative() === $orderId->toNative()),
                $this->isInstanceOf(Money::class),
                'cash'
            );

        ($this->handler)(RequestPayment::via($sessionId->toNative(), 5000, 'USD', 'cash'));

        $requested = array_values(array_filter(
            $this->eventStore->loadEvents($sessionId->toNative()),
            fn ($e) => $e instanceof PaymentRequested
        ));

        $this->assertCount(1, $requested);
        $this->assertSame('cash', $requested[0]->getPaymentMethod());
    }

    public function test_throws_when_payment_authorization_fails(): void
    {
        $sessionId = new SessionId();
        $orderId   = new OrderId();
        $this->buildCheckoutSession($sessionId, $orderId);

        $this->paymentService
            ->method('requestPaymentAuthorization')
            ->willReturn(false);

        $this->paymentService
            ->expects($this->never())
            ->method('applyPayment');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Payment authorization failed');

        ($this->handler)(RequestPayment::via($sessionId->toNative(), 5000, 'USD', 'cash'));
    }

    private function buildCheckoutSession(SessionId $sessionId, OrderId $orderId): void
    {
        $shiftId    = new ShiftId();
        $terminalId = new TerminalId();

        $startSession = new StartSessionHandler($this->sessionRepository);
        $startSession(StartSession::onTerminal(
            $sessionId->toNative(),
            $shiftId->toNative(),
            $terminalId->toNative()
        ));

        $startOrder = new StartNewOrderHandler($this->sessionRepository);
        $startOrder(StartNewOrder::withOrder(
            $sessionId->toNative(),
            $orderId->toNative()
        ));

        $session = $this->sessionRepository->load($sessionId);
        $session->initiateCheckout();
        $this->sessionRepository->store($session);
    }
}
