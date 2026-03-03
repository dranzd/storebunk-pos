<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class SyncOrderOnline extends AbstractCommand
{
    private function __construct(
        private readonly string $sessionId,
        private readonly string $orderId,
        private readonly string $branchId,
        private readonly ?string $customerId = null,
        string $commandId = ''
    ) {
        parent::__construct(
            $commandId,
            self::expectedMessageName(),
            [
                'session_id' => $this->sessionId,
                'order_id' => $this->orderId,
                'branch_id' => $this->branchId,
                'customer_id' => $this->customerId,
            ]
        );
    }

    final public static function forOrder(
        string $sessionId,
        string $orderId,
        string $branchId,
        ?string $customerId = null,
        ?string $commandId = null
    ): self
    {
        return new self($sessionId, $orderId, $branchId, $customerId, $commandId ?? '');
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.sync_order_online';
    }

    final public function sessionId(): SessionId
    {
        return SessionId::fromNative($this->sessionId);
    }

    final public function orderId(): OrderId
    {
        return OrderId::fromNative($this->orderId);
    }

    final public function branchId(): string
    {
        return $this->branchId;
    }

    final public function customerId(): ?string
    {
        return $this->customerId;
    }
}
