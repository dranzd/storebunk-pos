<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Stub\Service;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Service\PaymentServiceInterface;

final class StubPaymentService implements PaymentServiceInterface
{
    private bool $authorizationResult = true;
    private array $appliedPayments = [];

    public function requestPaymentAuthorization(
        OrderId $orderId,
        Money $amount,
        string $paymentMethod
    ): bool {
        return $this->authorizationResult;
    }

    public function applyPayment(OrderId $orderId, Money $amount, string $paymentMethod): void
    {
        if (!isset($this->appliedPayments[$orderId->toNative()])) {
            $this->appliedPayments[$orderId->toNative()] = [];
        }

        $this->appliedPayments[$orderId->toNative()][] = [
            'amount' => $amount->toArray(),
            'method' => $paymentMethod,
        ];
    }

    public function setAuthorizationResult(bool $result): void
    {
        $this->authorizationResult = $result;
    }

    public function getAppliedPayments(OrderId $orderId): array
    {
        return $this->appliedPayments[$orderId->toNative()] ?? [];
    }
}
