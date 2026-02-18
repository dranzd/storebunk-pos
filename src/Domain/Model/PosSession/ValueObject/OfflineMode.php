<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject;

enum OfflineMode: string
{
    case Online  = 'online';
    case Offline = 'offline';

    public function isOffline(): bool
    {
        return $this === self::Offline;
    }

    public function isOnline(): bool
    {
        return $this === self::Online;
    }
}
