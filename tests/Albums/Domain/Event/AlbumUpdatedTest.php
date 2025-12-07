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
}
