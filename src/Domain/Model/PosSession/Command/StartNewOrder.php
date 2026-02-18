<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class StartNewOrder extends AbstractCommand
{
    private SessionId $sessionId;
    private OrderId $orderId;

    public function __construct(SessionId $sessionId, OrderId $orderId)
    {
        $this->sessionId = $sessionId;
        $this->orderId = $orderId;

        parent::__construct(
            $sessionId->toNative(),
            self::expectedMessageName(),
            ['session_id' => $sessionId->toNative(), 'order_id' => $orderId->toNative()]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.start_new_order';
    }

    final public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function orderId(): OrderId
    {
        return $this->orderId;
    }
}
