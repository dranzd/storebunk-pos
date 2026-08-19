<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shared;

use Dranzd\StorebunkPos\Application\Shared\IdempotencyRegistry;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class IdempotencyRegistryTest extends TestCase
{
    private IdempotencyRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new IdempotencyRegistry();
    }

    public function test_new_command_id_has_not_been_processed(): void
    {
        $this->assertFalse($this->registry->hasBeenProcessed('command-uuid-1'));
    }

    public function test_marked_command_id_is_detected_as_processed(): void
    {
        $this->registry->markAsProcessed('command-uuid-1');

        $this->assertTrue($this->registry->hasBeenProcessed('command-uuid-1'));
    }

    public function test_different_command_ids_are_tracked_independently(): void
    {
        $this->registry->markAsProcessed('command-uuid-1');

        $this->assertTrue($this->registry->hasBeenProcessed('command-uuid-1'));
        $this->assertFalse($this->registry->hasBeenProcessed('command-uuid-2'));
    }

    public function test_multiple_command_ids_can_be_tracked(): void
    {
        $this->registry->markAsProcessed('command-uuid-1');
        $this->registry->markAsProcessed('command-uuid-2');
        $this->registry->markAsProcessed('command-uuid-3');

        $this->assertTrue($this->registry->hasBeenProcessed('command-uuid-1'));
        $this->assertTrue($this->registry->hasBeenProcessed('command-uuid-2'));
        $this->assertTrue($this->registry->hasBeenProcessed('command-uuid-3'));
    }

    public function test_the_same_id_claiming_different_work_is_refused(): void
    {
        $this->registry->markAsProcessed('one-key', 'create:order-1');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('cannot be reused');

        $this->registry->hasBeenProcessed('one-key', 'sync:order-1');
    }

    public function test_the_same_id_for_the_same_work_is_a_redelivery(): void
    {
        $this->registry->markAsProcessed('one-key', 'create:order-1');

        $this->assertTrue($this->registry->hasBeenProcessed('one-key', 'create:order-1'));
    }

    public function test_an_unstated_purpose_is_a_plain_lookup(): void
    {
        // Asking without stating a purpose is a question, not a claim — it
        // must not throw.
        $this->registry->markAsProcessed('one-key', 'create:order-1');

        $this->assertTrue($this->registry->hasBeenProcessed('one-key'));
        $this->assertFalse($this->registry->hasBeenProcessed('other-key'));
    }

    public function test_a_record_written_without_a_purpose_does_not_match_other_work(): void
    {
        // The wildcard trap: a bare mark used to record the id as matching
        // ANYTHING, so a replay that marked ids without saying what they did
        // silently disarmed the collision check for every one of them.
        $this->registry->markAsProcessed('bare-key');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('cannot be reused');

        $this->registry->hasBeenProcessed('bare-key', 'sync:order-1');
    }
}
