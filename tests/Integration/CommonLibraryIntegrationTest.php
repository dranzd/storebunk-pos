<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Tests\Integration;

use Dranzd\Common\Cqrs\Application\Command\Bus as CommandBus;
use Dranzd\Common\Cqrs\Application\Query\Bus as QueryBus;
use Dranzd\Common\Cqrs\Domain\Message\AbstractCommand;
use Dranzd\Common\Cqrs\Domain\Message\AbstractQuery;
use Dranzd\Common\Cqrs\Infrastructure\Bus\SimpleCommandBus;
use Dranzd\Common\Cqrs\Infrastructure\Bus\SimpleQueryBus;
use Dranzd\Common\Cqrs\Infrastructure\HandlerRegistry\InMemoryHandlerRegistry;
use Dranzd\Common\Domain\ValueObject\Identity\Uuid;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AbstractAggregateEvent;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRoot;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\AggregateRootTrait;
use Dranzd\Common\EventSourcing\Domain\EventSourcing\InMemoryEventStore;
use PHPUnit\Framework\TestCase;

/**
 * Integration test to verify common libraries work together correctly.
 *
 * This test ensures:
 * - dranzd/common-event-sourcing provides AggregateRoot, AggregateRootTrait, AbstractAggregateEvent, InMemoryEventStore
 * - dranzd/common-cqrs provides Command, Query, buses, handler registry
 * - dranzd/common-valueobject provides Uuid
 * - All libraries integrate correctly
 */
final class CommonLibraryIntegrationTest extends TestCase
{
    public function test_aggregate_root_trait_is_available(): void
    {
        $aggregate = new class implements AggregateRoot {
            use AggregateRootTrait;

            private string $id;

            public function __construct()
            {
                $this->id = 'test-123';
            }

            public function getAggregateRootUuid(): string
            {
                return $this->id;
            }
        };

        $this->assertInstanceOf(AggregateRoot::class, $aggregate);
        $this->assertSame('test-123', $aggregate->getAggregateRootUuid());
        $this->assertSame(0, $aggregate->getAggregateRootVersion());
    }

    public function test_abstract_aggregate_event_is_available(): void
    {
        $event = new class extends AbstractAggregateEvent {
            public static function expectedMessageName(): string
            {
                return 'test.event';
            }
        };

        $this->assertInstanceOf(AbstractAggregateEvent::class, $event);
    }

    public function test_in_memory_event_store_is_available(): void
    {
        $eventStore = new InMemoryEventStore();

        $this->assertFalse($eventStore->hasEvents('test-aggregate'));
        $this->assertSame([], $eventStore->loadEvents('test-aggregate'));
    }

    public function test_uuid_value_object_is_available(): void
    {
        $uuid = new Uuid();

        $this->assertNotEmpty($uuid->toNative());
        $this->assertIsString($uuid->toNative());
    }

    public function test_abstract_command_is_available(): void
    {
        $command = new class ('uuid-123', 'test.command', []) extends AbstractCommand {
            public static function expectedMessageName(): string
            {
                return 'test.command';
            }
        };

        $this->assertInstanceOf(AbstractCommand::class, $command);
        $this->assertSame('test.command', $command::expectedMessageName());
    }

    public function test_abstract_query_is_available(): void
    {
        $query = new class ('uuid-456', 'test.query', []) extends AbstractQuery {
            public static function expectedMessageName(): string
            {
                return 'test.query';
            }
        };

        $this->assertInstanceOf(AbstractQuery::class, $query);
        $this->assertSame('test.query', $query::expectedMessageName());
    }

    public function test_command_bus_infrastructure_is_available(): void
    {
        $handlerRegistry = new InMemoryHandlerRegistry();
        $commandBus = new SimpleCommandBus($handlerRegistry);

        $this->assertInstanceOf(CommandBus::class, $commandBus);
    }

    public function test_query_bus_infrastructure_is_available(): void
    {
        $handlerRegistry = new InMemoryHandlerRegistry();
        $queryBus = new SimpleQueryBus($handlerRegistry);

        $this->assertInstanceOf(QueryBus::class, $queryBus);
    }

    public function test_in_memory_handler_registry_is_available(): void
    {
        $registry = new InMemoryHandlerRegistry();

        $this->assertInstanceOf(InMemoryHandlerRegistry::class, $registry);
    }
}
