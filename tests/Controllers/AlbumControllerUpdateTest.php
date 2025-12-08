<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Repository\AlbumRepository;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Domain\Bus\EventBus;
use PHPUnit\Framework\TestCase;
use App\Controllers\AlbumController;
use App\Controllers\Http\Request;

final class AlbumControllerUpdateTest extends TestCase
{
    private $repository;
    private $eventBus;
    private $heroRepository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AlbumRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);
        $this->heroRepository = $this->createMock(HeroRepository::class);
    }

    public function testUpdate(): void
    {
        $album = Album::create('album-1', 'Original Name', 'cover.jpg');
        
        $this->repository->method('find')->willReturn($album);
        $this->repository->expects($this->once())->method('save');
        
        $controller = $this->createController();

        Request::withJsonBody(json_encode(['nombre' => 'Updated Name']));

        $payload = $this->capturePayload(fn () => $controller->update('album-1'));

        $this->assertEquals('éxito', $payload['estado']);
        $this->assertEquals('Updated Name', $payload['datos']['nombre']);
    }

    public function testUpdateWithCoverImage(): void
    {
        $album = Album::create('album-2', 'Album Name', 'old-cover.jpg');
        
        $this->repository->method('find')->willReturn($album);
        $this->repository->expects($this->once())->method('save');
        
        $controller = $this->createController();

        Request::withJsonBody(json_encode([
            'nombre' => 'Album Name',
            'coverImage' => 'new-cover.jpg'
        ]));

        $payload = $this->capturePayload(fn () => $controller->update('album-2'));

        $this->assertEquals('éxito', $payload['estado']);
        $this->assertEquals('new-cover.jpg', $payload['datos']['coverImage']);
    }

    public function testUpdateWithNullCoverImage(): void
    {
        $album = Album::create('album-3', 'Album Name', 'cover.jpg');
        
        $this->repository->method('find')->willReturn($album);
        $this->repository->expects($this->once())->method('save');
        
        $controller = $this->createController();

        Request::withJsonBody(json_encode([
            'coverImage' => null
        ]));

        $payload = $this->capturePayload(fn () => $controller->update('album-3'));

        $this->assertEquals('éxito', $payload['estado']);
    }

    public function testDestroy(): void
    {
        $album = Album::create('album-to-delete', 'Album Name', null);
        
        $this->repository->method('find')->willReturn($album);
        $this->repository->expects($this->once())->method('delete');
        $this->heroRepository->method('byAlbum')->willReturn([]);
        
        $controller = $this->createController();

        $payload = $this->capturePayload(fn () => $controller->destroy('album-to-delete'));

        $this->assertEquals('éxito', $payload['estado']);
        $this->assertStringContainsString('eliminado', $payload['datos']['message']);
    }

    public function testDestroyNotFound(): void
    {
        $this->repository->method('find')->willReturn(null);
        
        $controller = $this->createController();

        $payload = $this->capturePayload(fn () => $controller->destroy('nonexistent'));

        $this->assertEquals('error', $payload['estado']);
    }

    public function testStoreWithEmptyPayload(): void
    {
        $controller = $this->createController();

        Request::withJsonBody(json_encode([]));

        $payload = $this->capturePayload(fn () => $controller->store());

        $this->assertEquals('error', $payload['estado']);
    }

    private function createController(): AlbumController
    {
        return new AlbumController(
            new ListAlbumsUseCase($this->repository),
            new CreateAlbumUseCase($this->repository),
            new UpdateAlbumUseCase($this->repository, $this->eventBus),
            new DeleteAlbumUseCase($this->repository, $this->heroRepository),
            new FindAlbumUseCase($this->repository)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function capturePayload(callable $callable): array
    {
        ob_start();
        $result = $callable();
        $contents = (string) ob_get_clean();

        $payload = \App\Shared\Http\JsonResponse::lastPayload();

        if (is_array($result)) {
            return $result;
        }

        if ($payload !== null) {
            return $payload;
        }

        if ($contents !== '') {
            return json_decode($contents, true) ?? [];
        }

        return [];
    }
}
