<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Unit\Domain\Service;

use Dranzd\StorebunkPos\Domain\Service\DraftOrderContext;
use PHPUnit\Framework\TestCase;

final class DraftOrderContextTest extends TestCase
{
    public function test_it_returns_constructor_values_verbatim(): void
    {
        $values  = ['branch_id' => 'branch-1', 'customer_id' => null, 'tags' => ['a', 'b']];
        $context = new DraftOrderContext($values);

        $this->assertSame($values, $context->toArray());
        $this->assertSame('branch-1', $context->get('branch_id'));
        $this->assertSame(['a', 'b'], $context->get('tags'));
    }

    public function test_has_distinguishes_null_values_from_missing_keys(): void
    {
        $context = new DraftOrderContext(['customer_id' => null]);

        $this->assertTrue($context->has('customer_id'));
        $this->assertFalse($context->has('branch_id'));
        $this->assertNull($context->get('customer_id'));
        $this->assertSame('fallback', $context->get('branch_id', 'fallback'));
    }

    public function test_with_returns_a_new_instance_and_leaves_original_untouched(): void
    {
        $original = new DraftOrderContext(['branch_id' => 'branch-1']);
        $extended = $original->with('sales_channel', 'web');

        $this->assertNotSame($original, $extended);
        $this->assertSame(['branch_id' => 'branch-1'], $original->toArray());
        $this->assertSame(
            ['branch_id' => 'branch-1', 'sales_channel' => 'web'],
            $extended->toArray()
        );
    }

    public function test_defaults_to_an_empty_context(): void
    {
        $context = new DraftOrderContext();

        $this->assertSame([], $context->toArray());
        $this->assertFalse($context->has('anything'));
    }

    public function test_consumers_can_extend_it_with_typed_accessors(): void
    {
        $typed = new class (['branch_id' => 'branch-9']) extends DraftOrderContext {
            public function branchId(): string
            {
                return (string) $this->values['branch_id'];
            }
        };

        $this->assertSame('branch-9', $typed->branchId());
        $this->assertSame(['branch_id' => 'branch-9'], $typed->toArray());
    }
}
