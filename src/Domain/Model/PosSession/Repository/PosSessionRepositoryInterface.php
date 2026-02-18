<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\Repository;

use Dranzd\StorebunkPos\Domain\Model\PosSession\PosSession;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

interface PosSessionRepositoryInterface
{
    public function store(PosSession $session): void;

    public function load(SessionId $sessionId): PosSession;
}
