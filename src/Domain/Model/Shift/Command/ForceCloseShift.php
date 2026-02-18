<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\Shift\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;

final class ForceCloseShift extends AbstractCommand
{
    private ShiftId $shiftId;
    private string $supervisorId;
    private string $reason;

    public function __construct(ShiftId $shiftId, string $supervisorId, string $reason)
    {
        $this->shiftId = $shiftId;
        $this->supervisorId = $supervisorId;
        $this->reason = $reason;

        parent::__construct(
            $shiftId->toNative(),
            self::expectedMessageName(),
            ['shift_id' => $shiftId->toNative(), 'supervisor_id' => $supervisorId, 'reason' => $reason]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.shift.force_close';
    }

    final public function shiftId(): ShiftId
    {
        return $this->shiftId;
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
