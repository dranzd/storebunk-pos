<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class ResumeOrder extends AbstractCommand
{
    private function __construct(
        private readonly string $sessionId,
        private readonly string $orderId
    ) {
        parent::__construct(
            $this->sessionId,
            self::expectedMessageName(),
            [
                'session_id' => $this->sessionId,
                'order_id' => $this->orderId,
            ]
        );
    }

    final public static function withOrder(string $sessionId, string $orderId): self
    {
        return new self($sessionId, $orderId);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.resume_order';
    }

    final public function sessionId(): SessionId
    {
        return SessionId::fromNative($this->sessionId);
    }

    final public function orderId(): OrderId
    {
        return OrderId::fromNative($this->orderId);
    }
}
