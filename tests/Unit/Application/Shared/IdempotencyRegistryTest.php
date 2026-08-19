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
        $this->assertFalse($this->registry->hasBeenProcessed('command-uuid-1', 'work:command-uuid-1'));
    }

    public function test_marked_command_id_is_detected_as_processed(): void
    {
        $this->registry->markAsProcessed('command-uuid-1', 'work:command-uuid-1');

        $this->assertTrue($this->registry->hasBeenProcessed('command-uuid-1', 'work:command-uuid-1'));
    }

    public function test_different_command_ids_are_tracked_independently(): void
    {
        $this->registry->markAsProcessed('command-uuid-1', 'work:command-uuid-1');

        $this->assertTrue($this->registry->hasBeenProcessed('command-uuid-1', 'work:command-uuid-1'));
        $this->assertFalse($this->registry->hasBeenProcessed('command-uuid-2', 'work:command-uuid-2'));
    }

    public function test_multiple_command_ids_can_be_tracked(): void
    {
        $this->registry->markAsProcessed('command-uuid-1', 'work:command-uuid-1');
        $this->registry->markAsProcessed('command-uuid-2', 'work:command-uuid-2');
        $this->registry->markAsProcessed('command-uuid-3', 'work:command-uuid-3');

        $this->assertTrue($this->registry->hasBeenProcessed('command-uuid-1', 'work:command-uuid-1'));
        $this->assertTrue($this->registry->hasBeenProcessed('command-uuid-2', 'work:command-uuid-2'));
        $this->assertTrue($this->registry->hasBeenProcessed('command-uuid-3', 'work:command-uuid-3'));
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

    public function test_an_unknown_id_is_simply_unknown(): void
    {
        $this->registry->markAsProcessed('one-key', 'create:order-1');

        $this->assertFalse($this->registry->hasBeenProcessed('other-key', 'create:order-1'));
    }

    public function test_the_purpose_covers_the_target_not_just_the_command_type(): void
    {
        // Two creates for DIFFERENT orders sharing an id is a collision too.
        // A purpose built from the message name alone would call the second
        // one a redelivery and absorb it.
        $create = 'storebunk.pos.session.new_order_offline';
        $this->registry->markAsProcessed('one-key', IdempotencyRegistry::purposeFor($create, 'order-1'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('cannot be reused');

        $this->registry->hasBeenProcessed('one-key', IdempotencyRegistry::purposeFor($create, 'order-2'));
    }
}
