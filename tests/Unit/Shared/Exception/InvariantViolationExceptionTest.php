<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Shared\Exception;

use Dranzd\StorebunkPos\Shared\Exception\DomainException;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use PHPUnit\Framework\TestCase;

final class InvariantViolationExceptionTest extends TestCase
{
    public function test_it_extends_domain_exception(): void
    {
        $exception = InvariantViolationException::withMessage('Test invariant');

        $this->assertInstanceOf(DomainException::class, $exception);
    }

    public function test_it_can_be_created_with_message(): void
    {
        $message = 'Shift cannot close with unresolved orders';

        $exception = InvariantViolationException::withMessage($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function test_it_can_describe_business_rule_violations(): void
    {
        $exception = InvariantViolationException::withMessage(
            'One cashier can only have one open shift per terminal'
        );

        $this->assertStringContainsString('cashier', $exception->getMessage());
        $this->assertStringContainsString('shift', $exception->getMessage());
    }
}
