<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Application\PosSession\Command\RequestPayment;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Service\PaymentServiceInterface;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

/**
 * RequestPaymentHandler
 *
 * Handles the RequestPayment command by authorizing and applying a payment
 * against the session's active order.
 */
final class RequestPaymentHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly PaymentServiceInterface $paymentService
    ) {
    }

    public function __invoke(RequestPayment $command): void
    {
        $session = $this->sessionRepository->load(SessionId::fromNative($command->sessionId));
        $orderId = $session->activeOrderId();
        $amount = Money::fromArray(['amount' => $command->amount, 'currency' => $command->currency]);

        if ($orderId instanceof OrderId) {
            $authorized = $this->paymentService->requestPaymentAuthorization(
                $orderId,
                $amount,
                $command->paymentMethod
            );

            if (!$authorized) {
                throw InvariantViolationException::withMessage('Payment authorization failed');
            }
        }

        $session->requestPayment($amount, $command->paymentMethod);
        $this->sessionRepository->store($session);

        if ($orderId instanceof OrderId) {
            $this->paymentService->applyPayment(
                $orderId,
                $amount,
                $command->paymentMethod
            );
        }
    }
}
