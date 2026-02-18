<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject;

use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

enum SessionState: string
{
    case Idle = 'idle';
    case Building = 'building';
    case Checkout = 'checkout';

    final public function isIdle(): bool
    {
        return $this === self::Idle;
    }

    final public function isBuilding(): bool
    {
        return $this === self::Building;
    }

    final public function isCheckout(): bool
    {
        return $this === self::Checkout;
    }

    final public static function fromString(string $state): self
    {
        return self::tryFrom($state)
            ?? throw InvariantViolationException::withMessage(
                sprintf('Invalid session state: "%s"', $state)
            );
    }
}
