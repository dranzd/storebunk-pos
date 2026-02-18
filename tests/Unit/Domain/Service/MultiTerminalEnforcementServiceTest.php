<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Service;

use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
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
        $this->service->assertTerminalHasNoOpenShift(new TerminalId());
    }

    public function test_terminal_with_open_shift_throws(): void
    {
        $terminalId = new TerminalId();
        $this->service->registerOpenShift($terminalId, new ShiftId(), 'cashier-1');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift');

        $this->service->assertTerminalHasNoOpenShift($terminalId);
    }

    public function test_cashier_with_no_open_shift_passes(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->assertCashierHasNoOpenShift('cashier-1');
    }

    public function test_cashier_with_open_shift_on_another_terminal_throws(): void
    {
        $this->service->registerOpenShift(new TerminalId(), new ShiftId(), 'cashier-1');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('already has an open shift on another terminal');

        $this->service->assertCashierHasNoOpenShift('cashier-1');
    }

    public function test_order_without_binding_passes_any_terminal(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->assertOrderBelongsToTerminal(new OrderId(), new TerminalId());
    }

    public function test_order_bound_to_correct_terminal_passes(): void
    {
        $orderId = new OrderId();
        $terminalId = new TerminalId();
        $this->service->bindOrderToTerminal($orderId, $terminalId);

        $this->expectNotToPerformAssertions();
        $this->service->assertOrderBelongsToTerminal($orderId, $terminalId);
    }

    public function test_order_bound_to_different_terminal_throws(): void
    {
        $orderId = new OrderId();
        $terminalId1 = new TerminalId();
        $terminalId2 = new TerminalId();
        $this->service->bindOrderToTerminal($orderId, $terminalId1);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('is bound to terminal');

        $this->service->assertOrderBelongsToTerminal($orderId, $terminalId2);
    }

    public function test_unregistering_shift_allows_terminal_to_open_new_shift(): void
    {
        $terminalId = new TerminalId();
        $this->service->registerOpenShift($terminalId, new ShiftId(), 'cashier-1');
        $this->service->unregisterOpenShift($terminalId, 'cashier-1');

        $this->expectNotToPerformAssertions();
        $this->service->assertTerminalHasNoOpenShift($terminalId);
    }

    public function test_unregistering_shift_allows_cashier_to_open_new_shift(): void
    {
        $terminalId = new TerminalId();
        $this->service->registerOpenShift($terminalId, new ShiftId(), 'cashier-1');
        $this->service->unregisterOpenShift($terminalId, 'cashier-1');

        $this->expectNotToPerformAssertions();
        $this->service->assertCashierHasNoOpenShift('cashier-1');
    }

    public function test_unbinding_order_allows_access_from_any_terminal(): void
    {
        $orderId = new OrderId();
        $terminalId1 = new TerminalId();
        $terminalId2 = new TerminalId();
        $this->service->bindOrderToTerminal($orderId, $terminalId1);
        $this->service->unbindOrder($orderId);

        $this->expectNotToPerformAssertions();
        $this->service->assertOrderBelongsToTerminal($orderId, $terminalId2);
    }
}
