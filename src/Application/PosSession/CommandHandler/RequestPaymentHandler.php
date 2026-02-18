<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\CommandHandler;

use Dranzd\StorebunkPos\Domain\Model\PosSession\Command\RequestPayment;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Service\PaymentServiceInterface;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

final class RequestPaymentHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly PaymentServiceInterface $paymentService
    ) {
    }

    final public function __invoke(RequestPayment $command): void
    {
        $session = $this->sessionRepository->load($command->sessionId());

        $session->requestPayment($command->amount(), $command->paymentMethod());

        $events = $session->popRecordedEvents();
        $paymentEvent = end($events);

        if ($paymentEvent instanceof \Dranzd\StorebunkPos\Domain\Model\PosSession\Event\PaymentRequested) {
            $authorized = $this->paymentService->requestPaymentAuthorization(
                $paymentEvent->orderId(),
                $paymentEvent->amount(),
                $paymentEvent->paymentMethod()
            );

            if (!$authorized) {
                throw InvariantViolationException::withMessage('Payment authorization failed');
            }

            $this->paymentService->applyPayment(
                $paymentEvent->orderId(),
                $paymentEvent->amount(),
                $paymentEvent->paymentMethod()
            );
        }

        $this->sessionRepository->store($session);
    }
}
