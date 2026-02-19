<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class StartNewOrderOffline extends AbstractCommand
{
    public const MESSAGE_NAME = 'storebunk_pos.pos_session.command.start_new_order_offline';

    public function __construct(
        private readonly SessionId $sessionId,
        private readonly OrderId $orderId
    ) {
        parent::__construct('', self::MESSAGE_NAME, []);
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
