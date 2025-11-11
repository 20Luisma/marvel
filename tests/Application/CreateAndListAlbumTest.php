<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Albums\Application\DTO\CreateAlbumRequest;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;

final class CreateAndListAlbumTest extends TestCase
{
    public function testItCreatesAndListsAlbums(): void
    {
        $repository = new InMemoryAlbumRepository();
        $createUseCase = new CreateAlbumUseCase($repository);
        $listUseCase = new ListAlbumsUseCase($repository);

        $response = $createUseCase->execute(new CreateAlbumRequest('Album de Prueba'));

        self::assertSame('Album de Prueba', $response->toArray()['nombre']);

        $albums = $listUseCase->execute();
        self::assertCount(1, $albums);
        self::assertSame('Album de Prueba', $albums[0]['nombre']);
    }
}
