<?php

declare(strict_types=1);

namespace Tests\Albums\Application;

use App\Albums\Application\DTO\CreateAlbumRequest;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;

final class CreateAlbumUseCaseTest extends TestCase
{
    private CreateAlbumUseCase $useCase;
    private InMemoryAlbumRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryAlbumRepository();
        $this->useCase = new CreateAlbumUseCase($this->repository);
    }

    public function testItCreatesAnAlbum(): void
    {
        $request = new CreateAlbumRequest('Nuevo Álbum');

        $response = $this->useCase->execute($request);

        $responseData = $response->toArray();
        self::assertSame('Nuevo Álbum', $responseData['nombre']);
        self::assertArrayHasKey('coverImage', $responseData);
        self::assertNull($responseData['coverImage']);

        $savedAlbum = $this->repository->find($responseData['albumId']);
        self::assertNotNull($savedAlbum);
        self::assertSame('Nuevo Álbum', $savedAlbum->nombre());
        self::assertNull($savedAlbum->coverImage());
    }

    public function testItCreatesAnAlbumWithCoverImage(): void
    {
        $cover = 'https://picsum.photos/seed/marvel/800/400';
        $request = new CreateAlbumRequest('Marvel', $cover);

        $responseData = $this->useCase->execute($request)->toArray();

        self::assertSame('Marvel', $responseData['nombre']);
        self::assertSame($cover, $responseData['coverImage']);

        $savedAlbum = $this->repository->find($responseData['albumId']);
        self::assertNotNull($savedAlbum);
        self::assertSame($cover, $savedAlbum->coverImage());
    }
}
