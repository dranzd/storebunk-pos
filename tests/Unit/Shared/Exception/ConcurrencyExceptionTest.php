<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Shared\Exception;

use Dranzd\StorebunkPos\Shared\Exception\ConcurrencyException;
use Dranzd\StorebunkPos\Shared\Exception\DomainException;
use PHPUnit\Framework\TestCase;

final class ConcurrencyExceptionTest extends TestCase
{
    public function test_it_extends_domain_exception(): void
    {
        $exception = ConcurrencyException::forAggregate('test-id', 1, 2);

        $this->assertInstanceOf(DomainException::class, $exception);
    }

    public function test_it_can_be_created_with_version_mismatch_details(): void
    {
        $aggregateId = 'shift-789';
        $expectedVersion = 5;
        $actualVersion = 7;

        $exception = ConcurrencyException::forAggregate(
            $aggregateId,
            $expectedVersion,
            $actualVersion
        );

        $message = $exception->getMessage();
        $this->assertStringContainsString($aggregateId, $message);
        $this->assertStringContainsString((string) $expectedVersion, $message);
        $this->assertStringContainsString((string) $actualVersion, $message);
    }

    public function test_it_formats_message_correctly(): void
    {
        $exception = ConcurrencyException::forAggregate('terminal-123', 3, 5);

        $expectedMessage = 'Concurrency conflict for aggregate "terminal-123": expected version 3, but found version 5.';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }
}
