<?php

declare(strict_types=1);

namespace Storebunk\Shared\Domain;

use DateTimeImmutable;

interface DomainEvent
{
    public function aggregateId(): string;
    public function occurredOn(): DateTimeImmutable;
}
