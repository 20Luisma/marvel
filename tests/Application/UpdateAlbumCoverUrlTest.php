<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Albums\Application\DTO\UpdateAlbumRequest;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Domain\Entity\Album;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;

final class UpdateAlbumCoverUrlTest extends TestCase
{
    public function testItUpdatesCoverImageUsingUrl(): void
    {
        $albumRepository = new InMemoryAlbumRepository();
        $eventBus = new InMemoryEventBus();
        $album = Album::create('album-1', 'Marvel');
        $albumRepository->seed($album);

        $useCase = new UpdateAlbumUseCase($albumRepository, $eventBus);
        $cover = 'https://picsum.photos/seed/cover/800/400';

        $response = $useCase->execute(new UpdateAlbumRequest('album-1', null, $cover, true));
        $responseData = $response->toArray();

        self::assertSame($cover, $responseData['coverImage']);

        $stored = $albumRepository->find('album-1');
        self::assertNotNull($stored);
        self::assertSame($cover, $stored->coverImage());
    }
}
