<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class RequestPayment extends AbstractCommand
{
    private function __construct(
        private readonly string $sessionId,
        private readonly int $amount,
        private readonly string $currency,
        private readonly string $paymentMethod
    ) {
        parent::__construct(
            $this->sessionId,
            self::expectedMessageName(),
            [
                'session_id' => $this->sessionId,
                'amount' => [
                    'amount' => $this->amount,
                    'currency' => $this->currency,
                ],
                'payment_method' => $this->paymentMethod,
            ]
        );
    }

    final public static function via(string $sessionId, int $amount, string $currency, string $paymentMethod): self
    {
        return new self($sessionId, $amount, $currency, $paymentMethod);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.request_payment';
    }

    final public function sessionId(): SessionId
    {
        return SessionId::fromNative($this->sessionId);
    }

    final public function amount(): Money
    {
        return Money::fromArray(['amount' => $this->amount, 'currency' => $this->currency]);
    }

    final public function paymentMethod(): string
    {
        return $this->paymentMethod;
    }
}
