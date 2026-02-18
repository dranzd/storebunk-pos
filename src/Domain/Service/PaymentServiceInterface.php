<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;

interface PaymentServiceInterface
{
    public function requestPaymentAuthorization(
        OrderId $orderId,
        Money $amount,
        string $paymentMethod
    ): bool;

    public function applyPayment(OrderId $orderId, Money $amount, string $paymentMethod): void;
}
