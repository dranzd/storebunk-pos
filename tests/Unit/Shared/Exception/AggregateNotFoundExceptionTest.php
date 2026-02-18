<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Shared\Exception;

use Dranzd\StorebunkPos\Shared\Exception\AggregateNotFoundException;
use Dranzd\StorebunkPos\Shared\Exception\DomainException;
use PHPUnit\Framework\TestCase;

final class AggregateNotFoundExceptionTest extends TestCase
{
    public function test_it_extends_domain_exception(): void
    {
        $exception = AggregateNotFoundException::withId('test-id', 'TestAggregate');

        $this->assertInstanceOf(DomainException::class, $exception);
    }

    public function test_it_can_be_created_with_id_and_type(): void
    {
        $aggregateId = 'shift-123';
        $aggregateType = 'Shift';

        $exception = AggregateNotFoundException::withId($aggregateId, $aggregateType);

        $this->assertStringContainsString($aggregateId, $exception->getMessage());
        $this->assertStringContainsString($aggregateType, $exception->getMessage());
    }

    public function test_it_formats_message_correctly(): void
    {
        $exception = AggregateNotFoundException::withId('terminal-456', 'Terminal');

        $expectedMessage = 'Aggregate of type "Terminal" with ID "terminal-456" not found';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }
}
