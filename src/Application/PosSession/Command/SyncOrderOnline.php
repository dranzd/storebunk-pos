<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class SyncOrderOnline extends AbstractCommand
{
    public const MESSAGE_NAME = 'storebunk_pos.pos_session.command.sync_order_online';

    public function __construct(
        private readonly SessionId $sessionId,
        private readonly OrderId $orderId,
        private readonly string $branchId,
        private readonly ?string $customerId = null,
    ) {
        parent::__construct('', self::MESSAGE_NAME, []);
    }

    final public function getSessionId(): SessionId
    {
        return $this->sessionId;
    }

    final public function getOrderId(): OrderId
    {
        return $this->orderId;
    }

    final public function getBranchId(): string
    {
        return $this->branchId;
    }

    final public function getCustomerId(): ?string
    {
        return $this->customerId;
    }
}
