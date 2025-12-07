<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\Bus;

use App\Albums\Domain\Event\AlbumCreated;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DomainEventTest extends TestCase
{
    public function test_event_id_is_auto_generated_when_not_provided(): void
    {
        $event = new AlbumCreated('album-1', 'Test Album');

        $eventId = $event->eventId();

        $this->assertNotEmpty($eventId);
        $this->assertSame(16, strlen($eventId)); // 8 random bytes = 16 hex chars
    }

    public function test_event_id_uses_provided_value(): void
    {
        $event = new AlbumCreated('album-1', 'Test Album', 'custom-event-id');

        $this->assertSame('custom-event-id', $event->eventId());
    }

    public function test_aggregate_id_returns_correct_value(): void
    {
        $event = new AlbumCreated('my-aggregate-id', 'Test Album');

        $this->assertSame('my-aggregate-id', $event->aggregateId());
    }

    public function test_occurred_on_is_auto_set_when_not_provided(): void
    {
        $beforeCreation = new DateTimeImmutable();
        $event = new AlbumCreated('album-1', 'Test Album');
        $afterCreation = new DateTimeImmutable();

        $occurredOn = $event->occurredOn();

        // The occurred on should be between before and after
        $this->assertGreaterThanOrEqual($beforeCreation->getTimestamp(), $occurredOn->getTimestamp());
        $this->assertLessThanOrEqual($afterCreation->getTimestamp(), $occurredOn->getTimestamp());
    }

    public function test_occurred_on_uses_provided_value(): void
    {
        $specificTime = new DateTimeImmutable('2025-01-15 10:30:00');
        $event = new AlbumCreated('album-1', 'Test Album', null, $specificTime);

        $this->assertSame($specificTime, $event->occurredOn());
    }

    public function test_event_name_is_defined_by_child_class(): void
    {
        $eventName = AlbumCreated::eventName();

        $this->assertSame('album.created', $eventName);
    }

    public function test_to_primitives_returns_array_with_expected_keys(): void
    {
        $event = new AlbumCreated('album-1', 'Test Album');

        $primitives = $event->toPrimitives();

        $this->assertIsArray($primitives);
        $this->assertArrayHasKey('name', $primitives);
        $this->assertSame('Test Album', $primitives['name']);
    }

    public function test_from_primitives_creates_event_correctly(): void
    {
        $occurredOn = new DateTimeImmutable('2025-01-01 00:00:00');
        $body = ['name' => 'Reconstructed Album'];

        $event = AlbumCreated::fromPrimitives('album-123', $body, 'event-456', $occurredOn);

        $this->assertSame('album-123', $event->aggregateId());
        $this->assertSame('event-456', $event->eventId());
        $this->assertSame($occurredOn, $event->occurredOn());
        $this->assertSame('Reconstructed Album', $event->name());
    }

    public function test_base_from_primitives_throws_logic_exception(): void
    {
        // We need to test the base DomainEvent::fromPrimitives which throws LogicException
        // Create a concrete test class that doesn't override fromPrimitives
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('fromPrimitives() must be implemented');

        // Using reflection to call the base method
        // AlbumCreated overrides it, so we need a class that doesn't
        // Let's use the base class directly via the child that calls parent
        $occurredOn = new DateTimeImmutable();
        
        // Create an anonymous class that extends DomainEvent but uses parent fromPrimitives
        $anonymousEvent = new class('aggregate-id') extends \App\Shared\Domain\Bus\DomainEvent {
            public static function eventName(): string
            {
                return 'test.event';
            }

            public function toPrimitives(): array
            {
                return [];
            }
        };

        // Call the static fromPrimitives on the anonymous class (which will call parent's implementation)
        $class = get_class($anonymousEvent);
        $class::fromPrimitives('aggregate-id', [], null, null);
    }
}

