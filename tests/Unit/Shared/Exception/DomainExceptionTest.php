<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Shared\Exception;

use Dranzd\StorebunkPos\Shared\Exception\DomainException;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    public function test_it_is_an_exception(): void
    {
        $exception = new DomainException('Test message');

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(DomainException::class, $exception);
    }

    public function test_it_can_be_created_with_message(): void
    {
        $message = 'Something went wrong in the domain';
        $exception = new DomainException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function test_it_can_be_created_with_message_and_code(): void
    {
        $message = 'Domain error';
        $code = 42;
        $exception = new DomainException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    public function test_it_can_be_thrown_and_caught(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Test exception');

        throw new DomainException('Test exception');
    }
}
