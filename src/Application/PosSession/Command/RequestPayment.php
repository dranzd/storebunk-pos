<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class RequestPayment extends AbstractCommand
{
    private SessionId $sessionId;
    private Money $amount;
    private string $paymentMethod;

    public function __construct(SessionId $sessionId, Money $amount, string $paymentMethod)
    {
        $this->sessionId = $sessionId;
        $this->amount = $amount;
        $this->paymentMethod = $paymentMethod;

        parent::__construct(
            $sessionId->toNative(),
            self::expectedMessageName(),
            [
                'session_id' => $sessionId->toNative(),
                'amount' => $amount->toArray(),
                'payment_method' => $paymentMethod,
            ]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.request_payment';
    }

    final public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function amount(): Money
    {
        return $this->amount;
    }

    final public function paymentMethod(): string
    {
        return $this->paymentMethod;
    }
}
