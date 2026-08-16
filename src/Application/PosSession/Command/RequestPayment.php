<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command;

use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;

/**
 * RequestPayment
 *
 * Command to request payment for the active order in a session.
 */
final class RequestPayment extends AbstractCommand
{
    public function __construct(
        public readonly string $sessionId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $paymentMethod
    ) {
        parent::__construct(
            messageUuid: '',
            messageName: self::expectedMessageName(),
            payload: []
        );
    }

    public static function expectedMessageName(): string
    {
        return 'storebunk.pos.session.request_payment';
    }
}
