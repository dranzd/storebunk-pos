<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

/**
 * DraftOrderContext
 *
 * Opaque context bag forwarded to the Ordering BC when a draft order is
 * created. The keys and values belong to the CONSUMER and the Ordering BC —
 * POS must never read, type, or depend on them (see ADR-006).
 *
 * DO NOT add named fields for external-domain concepts (branch, customer,
 * sales channel, ...) to this class: that is domain leakage into POS. If the
 * Ordering BC needs more context tomorrow, the consumer adds a key here and
 * POS requires no change at all.
 *
 * Consumers may extend this class to layer typed accessors over the values
 * (the storage is protected for that purpose); the port contract stays this
 * base type.
 */
class DraftOrderContext
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        protected array $values = []
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function with(string $key, mixed $value): static
    {
        $clone = clone $this;
        $clone->values[$key] = $value;

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
