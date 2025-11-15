<?php

declare(strict_types=1);

namespace Tests\Albums\Domain\Event;

use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Event\AlbumCreated;
use PHPUnit\Framework\TestCase;

final class AlbumCreatedTest extends TestCase
{
    public function testForAlbumBuildsEvent(): void
    {
        $album = Album::create('album-1', 'Avengers', null);

        $event = AlbumCreated::forAlbum($album);

        self::assertSame('album-1', $event->aggregateId());
        self::assertSame('Avengers', $event->name());
        self::assertSame(['name' => 'Avengers'], $event->toPrimitives());
        self::assertSame('album.created', AlbumCreated::eventName());
    }

    public function testFromPrimitivesRestoresEventData(): void
    {
        $occurredOn = new \DateTimeImmutable('-1 day');
        $event = AlbumCreated::fromPrimitives('album-9', ['name' => 'X-Men'], 'event-123', $occurredOn);

        self::assertSame('album-9', $event->aggregateId());
        self::assertSame('X-Men', $event->name());
        self::assertSame('event-123', $event->eventId());
        self::assertSame($occurredOn->format(DATE_ATOM), $event->occurredOn()->format(DATE_ATOM));
    }
}
