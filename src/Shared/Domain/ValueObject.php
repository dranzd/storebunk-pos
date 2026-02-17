<?php

declare(strict_types=1);

namespace Storebunk\Shared\Domain;

abstract class ValueObject
{
    abstract public function equals(ValueObject $other): bool;
}
