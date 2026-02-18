<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Application\Shared;

use Dranzd\StorebunkPos\Application\Shared\IdempotencyRegistry;
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
}
