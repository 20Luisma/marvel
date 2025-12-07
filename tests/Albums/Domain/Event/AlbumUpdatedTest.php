<?php

declare(strict_types=1);

namespace Tests\Albums\Domain\Event;

use App\Albums\Domain\Event\AlbumUpdated;
use PHPUnit\Framework\TestCase;

final class AlbumUpdatedTest extends TestCase
{
    public function testBuildsPayload(): void
    {
        $event = new AlbumUpdated('album-1', 'name-old', 'name-new');

        self::assertSame('album.updated', $event->eventName());
        self::assertSame([
            'albumId' => 'album-1',
            'nombre' => 'name-old',
            'coverImage' => 'name-new',
            'occurredOn' => $event->occurredOn()->format(DATE_ATOM),
            'eventId' => $event->eventId(),
        ], $event->toPrimitives());
    }

    public function testFromPrimitivesCreatesEvent(): void
    {
        $occurredOn = new \DateTimeImmutable('2025-01-01 12:00:00');
        $body = [
            'nombre' => 'Reconstructed Album',
            'coverImage' => 'new-cover.jpg',
        ];

        $event = AlbumUpdated::fromPrimitives(
            'album-456',
            $body,
            'event-id-123',
            $occurredOn
        );

        self::assertSame('album-456', $event->aggregateId());
        self::assertSame('Reconstructed Album', $event->nombre());
        self::assertSame('new-cover.jpg', $event->coverImage());
        self::assertSame('event-id-123', $event->eventId());
        self::assertSame($occurredOn, $event->occurredOn());
    }

    public function testFromPrimitivesHandlesMissingValues(): void
    {
        $event = AlbumUpdated::fromPrimitives(
            'album-789',
            [], // Empty body
            null,
            null
        );

        self::assertSame('album-789', $event->aggregateId());
        self::assertSame('', $event->nombre());
        self::assertNull($event->coverImage());
    }

    public function testFromAlbumCreatesEvent(): void
    {
        $album = \App\Albums\Domain\Entity\Album::create('album-001', 'Marvel Heroes', 'marvel.jpg');

        $event = AlbumUpdated::fromAlbum($album);

        self::assertSame('album-001', $event->aggregateId());
        self::assertSame('Marvel Heroes', $event->nombre());
        self::assertSame('marvel.jpg', $event->coverImage());
    }
}
