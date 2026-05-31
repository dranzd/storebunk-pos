<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Event;

use DateTimeImmutable;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalActivated;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDecommissioned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalDisabled;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalMaintenanceSet;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalReassigned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRecommissioned;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRegistered;
use Dranzd\StorebunkPos\Domain\Model\Terminal\Event\TerminalRenamed;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\BranchId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\CheckoutInitiated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\NewOrderStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCancelledViaPOS;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCompleted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderCreatedOffline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderDeactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderMarkedPendingSync;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderParked;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderReactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderResumed;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderSyncedOnline;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\PaymentRequested;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionEnded;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\CashDropRecorded;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftAssigned;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftForceClosed;
use Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftOpened;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\CashierId;
use Dranzd\Common\Domain\ValueObject\Money\Basic as Money;
use PHPUnit\Framework\TestCase;

final class PayloadContractTest extends TestCase
{
    public function test_terminal_activated_payload_contract(): void
    {
        $event = TerminalActivated::occur(TerminalId::fromNative('550e8400-e29b-41d4-a716-446655440000'), new DateTimeImmutable('2025-04-06T09:30:00Z'));
        $this->verifyPayloadContract($event, TerminalActivated::class);
    }

    public function test_terminal_registered_payload_contract(): void
    {
        $event = TerminalRegistered::occur(TerminalId::fromNative('550e8400-e29b-41d4-a716-446655440001'), BranchId::fromNative('550e8400-e29b-41d4-a716-446655440002'), 'Terminal A', new DateTimeImmutable('2025-04-06T09:00:00Z'));
        $this->verifyPayloadContract($event, TerminalRegistered::class);
    }

    public function test_terminal_disabled_payload_contract(): void
    {
        $event = TerminalDisabled::occur(TerminalId::fromNative('550e8400-e29b-41d4-a716-446655440003'), new DateTimeImmutable('2025-04-06T11:00:00Z'));
        $this->verifyPayloadContract($event, TerminalDisabled::class);
    }

    public function test_terminal_maintenance_set_payload_contract(): void
    {
        $event = TerminalMaintenanceSet::occur(TerminalId::fromNative('550e8400-e29b-41d4-a716-446655440004'), new DateTimeImmutable('2025-04-06T12:00:00Z'));
        $this->verifyPayloadContract($event, TerminalMaintenanceSet::class);
    }

    public function test_terminal_reassigned_payload_contract(): void
    {
        $event = TerminalReassigned::occur(TerminalId::fromNative('550e8400-e29b-41d4-a716-446655440005'), BranchId::fromNative('550e8400-e29b-41d4-a716-446655440006'), BranchId::fromNative('550e8400-e29b-41d4-a716-446655440007'), new DateTimeImmutable('2025-04-06T13:00:00Z'));
        $this->verifyPayloadContract($event, TerminalReassigned::class);
    }

    public function test_terminal_decommissioned_payload_contract(): void
    {
        $event = TerminalDecommissioned::occur(TerminalId::fromNative('550e8400-e29b-41d4-a716-446655440008'), 'End of life', new DateTimeImmutable('2025-04-06T14:00:00Z'));
        $this->verifyPayloadContract($event, TerminalDecommissioned::class);
    }

    public function test_terminal_recommissioned_payload_contract(): void
    {
        $event = TerminalRecommissioned::occur(TerminalId::fromNative('550e8400-e29b-41d4-a716-446655440009'), 'Repair complete', new DateTimeImmutable('2025-04-06T15:00:00Z'));
        $this->verifyPayloadContract($event, TerminalRecommissioned::class);
    }

    public function test_terminal_renamed_payload_contract(): void
    {
        $event = TerminalRenamed::occur(TerminalId::fromNative('550e8400-e29b-41d4-a716-446655440010'), 'Old Name', 'New Name', new DateTimeImmutable('2025-04-06T16:00:00Z'));
        $this->verifyPayloadContract($event, TerminalRenamed::class);
    }

    public function test_session_started_payload_contract(): void
    {
        $event = SessionStarted::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440011'), ShiftId::fromNative('550e8400-e29b-41d4-a716-446655440012'), TerminalId::fromNative('550e8400-e29b-41d4-a716-446655440013'), \Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId::fromNative('550e8400-e29b-41d4-a716-446655440099'), new DateTimeImmutable('2025-04-06T10:00:00Z'));
        $this->verifyPayloadContract($event, SessionStarted::class);
    }

    public function test_session_ended_payload_contract(): void
    {
        $event = SessionEnded::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440014'), new DateTimeImmutable('2025-04-06T17:00:00Z'));
        $this->verifyPayloadContract($event, SessionEnded::class);
    }

    public function test_checkout_initiated_payload_contract(): void
    {
        $event = CheckoutInitiated::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440015'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440016'), new DateTimeImmutable('2025-04-06T18:00:00Z'));
        $this->verifyPayloadContract($event, CheckoutInitiated::class);
    }

    public function test_new_order_started_payload_contract(): void
    {
        $event = NewOrderStarted::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440017'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440018'), new DateTimeImmutable('2025-04-06T19:00:00Z'));
        $this->verifyPayloadContract($event, NewOrderStarted::class);
    }

    public function test_order_cancelled_via_pos_payload_contract(): void
    {
        $event = OrderCancelledViaPOS::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440019'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440020'), 'Customer request', new DateTimeImmutable('2025-04-06T20:00:00Z'));
        $this->verifyPayloadContract($event, OrderCancelledViaPOS::class);
    }

    public function test_order_completed_payload_contract(): void
    {
        $event = OrderCompleted::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440021'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440022'), new DateTimeImmutable('2025-04-06T21:00:00Z'));
        $this->verifyPayloadContract($event, OrderCompleted::class);
    }

    public function test_order_created_offline_payload_contract(): void
    {
        $event = OrderCreatedOffline::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440023'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440024'), 'cmd-123', new DateTimeImmutable('2025-04-06T22:00:00Z'));
        $this->verifyPayloadContract($event, OrderCreatedOffline::class);
    }

    public function test_order_deactivated_payload_contract(): void
    {
        $event = OrderDeactivated::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440025'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440026'), 'Deactivation reason', new DateTimeImmutable('2025-04-06T23:00:00Z'));
        $this->verifyPayloadContract($event, OrderDeactivated::class);
    }

    public function test_order_marked_pending_sync_payload_contract(): void
    {
        $event = OrderMarkedPendingSync::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440027'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440028'), new DateTimeImmutable('2025-04-07T00:00:00Z'));
        $this->verifyPayloadContract($event, OrderMarkedPendingSync::class);
    }

    public function test_order_parked_payload_contract(): void
    {
        $event = OrderParked::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440029'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440030'), new DateTimeImmutable('2025-04-07T01:00:00Z'));
        $this->verifyPayloadContract($event, OrderParked::class);
    }

    public function test_order_reactivated_payload_contract(): void
    {
        $event = OrderReactivated::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440031'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440032'), new DateTimeImmutable('2025-04-07T02:00:00Z'));
        $this->verifyPayloadContract($event, OrderReactivated::class);
    }

    public function test_order_resumed_payload_contract(): void
    {
        $event = OrderResumed::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440033'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440034'), new DateTimeImmutable('2025-04-07T03:00:00Z'));
        $this->verifyPayloadContract($event, OrderResumed::class);
    }

    public function test_order_synced_online_payload_contract(): void
    {
        $event = OrderSyncedOnline::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440035'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440036'), new DateTimeImmutable('2025-04-07T04:00:00Z'));
        $this->verifyPayloadContract($event, OrderSyncedOnline::class);
    }

    public function test_payment_requested_payload_contract(): void
    {
        $event = PaymentRequested::occur(SessionId::fromNative('550e8400-e29b-41d4-a716-446655440037'), OrderId::fromNative('550e8400-e29b-41d4-a716-446655440038'), Money::fromArray(['amount' => 100, 'currency' => 'USD']), 'credit_card', new DateTimeImmutable('2025-04-07T05:00:00Z'));
        $this->verifyPayloadContract($event, PaymentRequested::class);
    }

    public function test_shift_opened_payload_contract(): void
    {
        $event = ShiftOpened::occur(ShiftId::fromNative('550e8400-e29b-41d4-a716-446655440039'), TerminalId::fromNative('550e8400-e29b-41d4-a716-446655440040'), BranchId::fromNative('550e8400-e29b-41d4-a716-446655440041'), CashierId::fromNative('550e8400-e29b-41d4-a716-446655440042'), Money::fromArray(['amount' => 500, 'currency' => 'USD']), new DateTimeImmutable('2025-04-06T08:00:00Z'));
        $this->verifyPayloadContract($event, ShiftOpened::class);
    }

    public function test_shift_closed_payload_contract(): void
    {
        $event = ShiftClosed::occur(ShiftId::fromNative('550e8400-e29b-41d4-a716-446655440043'), Money::fromArray(['amount' => 500, 'currency' => 'USD']), Money::fromArray(['amount' => 500, 'currency' => 'USD']), Money::fromArray(['amount' => 0, 'currency' => 'USD']), new DateTimeImmutable('2025-04-07T06:00:00Z'));
        $this->verifyPayloadContract($event, ShiftClosed::class);
    }

    public function test_shift_force_closed_payload_contract(): void
    {
        $event = ShiftForceClosed::occur(ShiftId::fromNative('550e8400-e29b-41d4-a716-446655440044'), '550e8400-e29b-41d4-a716-446655440046', 'System timeout', new DateTimeImmutable('2025-04-07T07:00:00Z'));
        $this->verifyPayloadContract($event, ShiftForceClosed::class);
    }

    public function test_cash_drop_recorded_payload_contract(): void
    {
        $event = CashDropRecorded::occur(ShiftId::fromNative('550e8400-e29b-41d4-a716-446655440045'), Money::fromArray(['amount' => 250, 'currency' => 'USD']), new DateTimeImmutable('2025-04-07T08:00:00Z'));
        $this->verifyPayloadContract($event, CashDropRecorded::class);
    }

    public function test_shift_assigned_payload_contract(): void
    {
        $event = ShiftAssigned::occur(
            ShiftId::fromNative('550e8400-e29b-41d4-a716-446655440047'),
            CashierId::fromNative('550e8400-e29b-41d4-a716-446655440048'),
            [
                CashierId::fromNative('550e8400-e29b-41d4-a716-446655440049'),
                CashierId::fromNative('550e8400-e29b-41d4-a716-446655440050'),
            ],
            new DateTimeImmutable('2025-04-07T09:00:00Z')
        );
        $this->verifyPayloadContract($event, ShiftAssigned::class);
    }

    private function verifyPayloadContract(object $event, string $eventClass): void
    {
        $payload = $event->getPayload();
        $this->assertNotEmpty($payload, "$eventClass::getPayload() must not return empty array");
        $this->assertIsArray($payload, "$eventClass::getPayload() must return array");

        $serialized = $event->toArray();
        $this->assertArrayHasKey('payload', $serialized, "Serialized $eventClass must have 'payload' key");
        $this->assertNotEmpty($serialized['payload'], "Serialized $eventClass must have non-empty payload");

        $restored = $eventClass::fromArray($serialized);
        $this->assertSame($payload, $restored->getPayload(), "$eventClass payload must be preserved through serialization cycle");
    }
}
