<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\RequestPayment;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
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
        $orderId = $session->activeOrderId();

        if ($orderId instanceof OrderId) {
            $authorized = $this->paymentService->requestPaymentAuthorization(
                $orderId,
                $command->amount(),
                $command->paymentMethod()
            );

            if (!$authorized) {
                throw InvariantViolationException::withMessage('Payment authorization failed');
            }
        }

        $session->requestPayment($command->amount(), $command->paymentMethod());
        $this->sessionRepository->store($session);

        if ($orderId instanceof OrderId) {
            $this->paymentService->applyPayment(
                $orderId,
                $command->amount(),
                $command->paymentMethod()
            );
        }
    }
}
