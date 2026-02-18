<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;

final class CompleteOrder extends AbstractCommand
{
    private SessionId $sessionId;

    public function __construct(SessionId $sessionId)
    {
        $this->sessionId = $sessionId;

        parent::__construct(
            $sessionId->toNative(),
            self::expectedMessageName(),
            ['session_id' => $sessionId->toNative()]
        );
    }

    final public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.complete_order';
    }

    final public function sessionId(): SessionId
    {
        return $this->sessionId;
    }
}
