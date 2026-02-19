<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Integration;

use DateTimeImmutable;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\ReactivateOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartNewOrderHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\Handler\StartSessionHandler;
use Dranzd\StorebunkPos\Application\PosSession\Command\ReactivateOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartNewOrder;
use Dranzd\StorebunkPos\Application\PosSession\Command\StartSession;
use Dranzd\Common\Cqrs\Application\Command\Bus as CommandBus;
use Dranzd\StorebunkPos\Application\PosSession\ReadModel\PosSessionReadModelInterface;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\NewOrderStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderDeactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\OrderReactivated;
use Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\OrderId;
use Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\SessionId;
use Dranzd\StorebunkPos\Domain\Model\Shift\ValueObject\ShiftId;
use Dranzd\StorebunkPos\Domain\Model\Terminal\ValueObject\TerminalId;
use Dranzd\StorebunkPos\Domain\Service\DraftLifecycleService;
use Dranzd\StorebunkPos\Infrastructure\PosSession\Repository\InMemoryPosSessionRepository;
use Dranzd\StorebunkPos\Shared\Exception\InvariantViolationException;
use Dranzd\StorebunkPos\Tests\Stub\Service\StubInventoryService;
use Dranzd\StorebunkPos\Tests\Stub\Service\StubOrderingService;
use PHPUnit\Framework\TestCase;

final class DraftLifecycleIntegrationTest extends TestCase
{
    private InMemoryPosSessionRepository $sessionRepository;
    private StubInventoryService $inventoryService;
    private StubOrderingService $orderingService;
    private DraftLifecycleService $lifecycleService;

    protected function setUp(): void
    {
        $eventStore = new InMemoryEventStore();
        $this->sessionRepository = new InMemoryPosSessionRepository($eventStore);
        $this->inventoryService = new StubInventoryService();
        $this->orderingService = new StubOrderingService();

        $stubReadModel = new class implements PosSessionReadModelInterface {
            public function getSessionsWithActiveOrder(): array
            {
                return [];
            }

            public function findActiveByShiftId(string $shiftId): array
            {
                return [];
            }
        };
        $stubCommandBus = new class implements CommandBus {
            public function dispatch(object $command): void {}
        };

        $this->lifecycleService = new DraftLifecycleService($stubReadModel, $stubCommandBus);
    }

    public function test_full_draft_lifecycle_with_deactivation_and_reactivation(): void
    {
        $sessionId = new SessionId();
        $shiftId = new ShiftId();
        $terminalId = new TerminalId();
        $orderId = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(new StartSession($sessionId, $shiftId, $terminalId));

        $startOrderHandler = new StartNewOrderHandler($this->sessionRepository);
        $startOrderHandler(new StartNewOrder($sessionId, $orderId));

        $session = $this->sessionRepository->load($sessionId);
        $session->deactivateOrder('TTL expired');
        $this->sessionRepository->store($session);

        $this->inventoryService->createSoftReservation($orderId);

        $reactivateHandler = new ReactivateOrderHandler($this->sessionRepository, $this->inventoryService);
        $reactivateHandler(new ReactivateOrder($sessionId, $orderId));

        $session = $this->sessionRepository->load($sessionId);
        $this->assertTrue($this->inventoryService->hasSoftReservation($orderId));
    }

    public function test_reactivation_fails_when_inventory_unavailable(): void
    {
        $sessionId = new SessionId();
        $shiftId = new ShiftId();
        $terminalId = new TerminalId();
        $orderId = new OrderId();

        $startSessionHandler = new StartSessionHandler($this->sessionRepository);
        $startSessionHandler(new StartSession($sessionId, $shiftId, $terminalId));

        $startOrderHandler = new StartNewOrderHandler($this->sessionRepository);
        $startOrderHandler(new StartNewOrder($sessionId, $orderId));

        $session = $this->sessionRepository->load($sessionId);
        $session->deactivateOrder('TTL expired');
        $this->sessionRepository->store($session);

        $this->inventoryService->setReReservationResult(false);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Cannot reactivate order: insufficient inventory for re-reservation');

        $reactivateHandler = new ReactivateOrderHandler($this->sessionRepository, $this->inventoryService);
        $reactivateHandler(new ReactivateOrder($sessionId, $orderId));
    }

    public function test_draft_lifecycle_service_detects_expired_orders(): void
    {
        $lastActivity = new DateTimeImmutable('2024-01-01 10:00:00');
        $currentTime = new DateTimeImmutable('2024-01-01 11:30:00');

        $isExpired = $this->lifecycleService->isOrderExpired($lastActivity, $currentTime);

        $this->assertTrue($isExpired);
    }

    public function test_draft_lifecycle_service_detects_inactive_orders(): void
    {
        $lastActivity = new DateTimeImmutable('2024-01-01 10:00:00');
        $currentTime = new DateTimeImmutable('2024-01-01 10:20:00');

        $shouldDeactivate = $this->lifecycleService->shouldDeactivateOrder($lastActivity, $currentTime);

        $this->assertTrue($shouldDeactivate);
    }

    public function test_draft_lifecycle_service_does_not_deactivate_recent_orders(): void
    {
        $lastActivity = new DateTimeImmutable('2024-01-01 10:00:00');
        $currentTime = new DateTimeImmutable('2024-01-01 10:10:00');

        $shouldDeactivate = $this->lifecycleService->shouldDeactivateOrder($lastActivity, $currentTime);

        $this->assertFalse($shouldDeactivate);
    }
}
