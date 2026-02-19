<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Service;

final class DraftOrderContext
{
    public function __construct(
        public readonly string $branchId,
        public readonly ?string $customerId = null,
    ) {
    }
}
