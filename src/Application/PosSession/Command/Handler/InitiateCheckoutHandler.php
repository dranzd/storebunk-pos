<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Application\PosSession\Command\Handler;

use Dranzd\StorebunkPos\Application\PosSession\Command\InitiateCheckout;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Repository\PosSessionRepositoryInterface;
use Dranzd\StorebunkPos\Domain\Service\InventoryServiceInterface;
use Dranzd\StorebunkPos\Domain\Service\OrderingServiceInterface;

final class InitiateCheckoutHandler
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $sessionRepository,
        private readonly OrderingServiceInterface $orderingService,
        private readonly InventoryServiceInterface $inventoryService
    ) {
    }

    final public function __invoke(InitiateCheckout $command): void
    {
        $session = $this->sessionRepository->load($command->sessionId());
        $session->initiateCheckout();

        $events = $session->popRecordedEvents();
        $checkoutEvent = end($events);

        if ($checkoutEvent instanceof \Dranzd\StorebunkPos\Domain\Model\PosSession\Event\CheckoutInitiated) {
            $orderId = $checkoutEvent->orderId();
            $this->orderingService->confirmOrder($orderId);
            $this->inventoryService->confirmReservation($orderId);
        }

        $this->sessionRepository->store($session);
    }
}
