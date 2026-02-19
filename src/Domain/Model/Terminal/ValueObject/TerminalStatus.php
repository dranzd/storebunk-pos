<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject;

use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;

enum TerminalStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Maintenance = 'maintenance';
    case Decommissioned = 'decommissioned';

    final public function isActive(): bool
    {
        return $this === self::Active;
    }

    final public function isDisabled(): bool
    {
        return $this === self::Disabled;
    }

    final public function isMaintenance(): bool
    {
        return $this === self::Maintenance;
    }

    final public function isDecommissioned(): bool
    {
        return $this === self::Decommissioned;
    }

    final public static function fromString(string $status): self
    {
        return self::tryFrom($status)
            ?? throw InvariantViolationException::withMessage(
                sprintf('Invalid terminal status: "%s"', $status)
            );
    }
}
