<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class PendingSyncQueue
{
    /** @var array<string, array{sessionId: SessionId, orderId: OrderId, commandId: string, queuedAt: \DateTimeImmutable}> */
    private array $queue = [];

    public function enqueue(SessionId $sessionId, OrderId $orderId, string $commandId): void
    {
        $this->queue[$orderId->toNative()] = [
            'sessionId' => $sessionId,
            'orderId'   => $orderId,
            'commandId' => $commandId,
            'queuedAt'  => new \DateTimeImmutable(),
        ];
    }

    public function dequeueByOrderId(OrderId $orderId): void
    {
        unset($this->queue[$orderId->toNative()]);
    }

    public function hasByOrderId(OrderId $orderId): bool
    {
        return isset($this->queue[$orderId->toNative()]);
    }

    public function hasCommandId(string $commandId): bool
    {
        foreach ($this->queue as $entry) {
            if ($entry['commandId'] === $commandId) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, array{sessionId: SessionId, orderId: OrderId, commandId: string, queuedAt: \DateTimeImmutable}> */
    public function all(): array
    {
        return $this->queue;
    }

    public function count(): int
    {
        return count($this->queue);
    }

    public function isEmpty(): bool
    {
        return $this->queue === [];
    }
}
