<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Service\ShiftClosePolicy;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class ShiftClosePolicyTest extends TestCase
{
    private ShiftClosePolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ShiftClosePolicy();
    }

    public function test_allows_close_when_no_active_sessions(): void
    {
        $this->expectNotToPerformAssertions();
        $this->policy->assertCanClose(new ShiftId(), []);
    }

    public function test_blocks_close_when_one_active_session_exists(): void
    {
        $shiftId = new ShiftId();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot close shift');
        $this->expectExceptionMessage('1 active POS session(s)');

        $this->policy->assertCanClose($shiftId, ['session-uuid-1']);
    }

    public function test_blocks_close_when_multiple_active_sessions_exist(): void
    {
        $shiftId = new ShiftId();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('3 active POS session(s)');

        $this->policy->assertCanClose($shiftId, ['s1', 's2', 's3']);
    }

    public function test_error_message_includes_shift_id(): void
    {
        $shiftId = new ShiftId();

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage($shiftId->toNative());

        $this->policy->assertCanClose($shiftId, ['session-uuid-1']);
    }
}
