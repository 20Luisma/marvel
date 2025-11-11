<?php

declare(strict_types=1);

namespace Tests\Albums\Application;

use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Event\AlbumUpdated;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;

final class FindAlbumUseCaseTest extends TestCase
{
    public function testItReturnsDtoWhenAlbumExists(): void
    {
        $repository = new InMemoryAlbumRepository();
        $repository->save(Album::create('album-1', 'Marvel Studios'));

        $useCase = new FindAlbumUseCase($repository);
        $result = $useCase->execute('album-1');

        self::assertSame('album-1', $result['albumId']);
        self::assertSame('Marvel Studios', $result['nombre']);
    }

    public function testItThrowsWhenAlbumDoesNotExist(): void
    {
        $useCase = new FindAlbumUseCase(new InMemoryAlbumRepository());

        $this->expectException(InvalidArgumentException::class);
        $useCase->execute('missing');
    }
}

final class AlbumUpdatedEventTest extends TestCase
{
    public function testAlbumUpdatedSerializesAndRestoresState(): void
    {
        $album = Album::create('album-2', 'Guardians', 'cover.png');
        $event = AlbumUpdated::fromAlbum($album);

        self::assertSame('album.updated', AlbumUpdated::eventName());

        $payload = $event->toPrimitives();
        self::assertSame('Guardians', $payload['nombre']);
        self::assertSame('cover.png', $payload['coverImage']);

        $restored = AlbumUpdated::fromPrimitives(
            $payload['albumId'],
            $payload,
            $payload['eventId'],
            new \DateTimeImmutable($payload['occurredOn'])
        );

        self::assertSame($event->nombre(), $restored->nombre());
        self::assertSame($event->coverImage(), $restored->coverImage());
    }
}
