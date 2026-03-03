<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class ForceCloseShift extends AbstractCommand
{
    private function __construct(
        private readonly string $shiftId,
        private readonly string $supervisorId,
        private readonly string $reason
    ) {
        parent::__construct(
            $this->shiftId,
            self::expectedMessageName(),
            [
                'shift_id' => $this->shiftId,
                'supervisor_id' => $this->supervisorId,
                'reason' => $this->reason,
            ]
        );
    }

    final public static function bySupervisor(string $shiftId, string $supervisorId, string $reason): self
    {
        return new self($shiftId, $supervisorId, $reason);
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.force_close';
    }

    final public function shiftId(): ShiftId
    {
        return ShiftId::fromString($this->shiftId);
    }

    final public function supervisorId(): string
    {
        return $this->supervisorId;
    }

    final public function reason(): string
    {
        return $this->reason;
    }
}
