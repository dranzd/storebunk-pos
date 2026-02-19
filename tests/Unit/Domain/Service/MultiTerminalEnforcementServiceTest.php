<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\MultiTerminalEnforcementService;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class MultiTerminalEnforcementServiceTest extends TestCase
{
    private MultiTerminalEnforcementService $service;

    protected function setUp(): void
    {
        $this->service = new MultiTerminalEnforcementService();
    }

    public function test_terminal_with_no_open_shift_passes(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->assertTerminalHasNoOpenShift(new TerminalId(), []);
    }

    public function test_terminal_with_open_shift_throws(): void
    {
        $terminalId = new TerminalId();
        $openShifts = [$terminalId->toNative() => 'shift-uuid-1'];

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');

        $this->service->assertTerminalHasNoOpenShift($terminalId, $openShifts);
    }

    public function test_cashier_with_no_open_shift_passes(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->assertCashierHasNoOpenShift('cashier-1', []);
    }

    public function test_cashier_with_open_shift_on_another_terminal_throws(): void
    {
        $activeTerminals = ['cashier-1' => 'terminal-uuid-1'];

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift on another terminal');

        $this->service->assertCashierHasNoOpenShift('cashier-1', $activeTerminals);
    }

    public function test_order_without_binding_passes_any_terminal(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->assertOrderBelongsToTerminal(new OrderId(), new TerminalId(), []);
    }

    public function test_order_bound_to_correct_terminal_passes(): void
    {
        $orderId = new OrderId();
        $terminalId = new TerminalId();
        $binding = [$orderId->toNative() => $terminalId->toNative()];

        $this->expectNotToPerformAssertions();
        $this->service->assertOrderBelongsToTerminal($orderId, $terminalId, $binding);
    }

    public function test_order_bound_to_different_terminal_throws(): void
    {
        $orderId = new OrderId();
        $terminalId1 = new TerminalId();
        $terminalId2 = new TerminalId();
        $binding = [$orderId->toNative() => $terminalId1->toNative()];

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('is bound to terminal');

        $this->service->assertOrderBelongsToTerminal($orderId, $terminalId2, $binding);
    }

    public function test_terminal_not_in_open_shifts_passes_after_shift_closed(): void
    {
        $terminalId = new TerminalId();
        $openShifts = [];

        $this->expectNotToPerformAssertions();
        $this->service->assertTerminalHasNoOpenShift($terminalId, $openShifts);
    }

    public function test_cashier_not_in_active_terminals_passes_after_shift_closed(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->assertCashierHasNoOpenShift('cashier-1', []);
    }

    public function test_order_not_in_binding_passes_any_terminal_after_unbind(): void
    {
        $orderId = new OrderId();
        $terminalId2 = new TerminalId();

        $this->expectNotToPerformAssertions();
        $this->service->assertOrderBelongsToTerminal($orderId, $terminalId2, []);
    }
}
