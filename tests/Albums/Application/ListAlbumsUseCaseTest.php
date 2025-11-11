<?php

declare(strict_types=1);

namespace Tests\Albums\Application;

use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Domain\Entity\Album;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;

final class ListAlbumsUseCaseTest extends TestCase
{
    public function testItReturnsAlbumsOrderedByCreationDate(): void
    {
        $repository = new InMemoryAlbumRepository();

        $older = Album::fromPrimitives([
            'albumId' => 'album-1',
            'nombre' => 'First',
            'createdAt' => '2023-01-01T10:00:00+00:00',
            'updatedAt' => '2023-01-01T10:00:00+00:00',
            'coverImage' => null,
        ]);

        $newer = Album::fromPrimitives([
            'albumId' => 'album-2',
            'nombre' => 'Second',
            'createdAt' => '2024-01-01T10:00:00+00:00',
            'updatedAt' => '2024-01-01T10:00:00+00:00',
            'coverImage' => 'https://example.com/cover.jpg',
        ]);

        $repository->save($newer);
        $repository->save($older);

        $useCase = new ListAlbumsUseCase($repository);
        $result = $useCase->execute();

        self::assertSame(['First', 'Second'], array_column($result, 'nombre'));
        self::assertNull($result[0]['coverImage']);
        self::assertSame('https://example.com/cover.jpg', $result[1]['coverImage']);
    }
}
