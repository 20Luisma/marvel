<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Domain\Entity\Album;
use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use App\Controllers\AlbumController;
use App\Controllers\Http\Request;
use Tests\Doubles\InMemoryAlbumRepository;

final class AlbumControllerExtendedTest extends TestCase
{
    private InMemoryAlbumRepository $albums;
    private AlbumController $controller;

    protected function setUp(): void
    {
        $this->albums = new InMemoryAlbumRepository();
        $eventBus = new InMemoryEventBus();

        $this->controller = new AlbumController(
            new ListAlbumsUseCase($this->albums),
            new CreateAlbumUseCase($this->albums),
            new UpdateAlbumUseCase($this->albums, $eventBus),
            new DeleteAlbumUseCase($this->albums, new HeroRepositoryStub()),
            new FindAlbumUseCase($this->albums)
        );
    }

    public function testUpdateRenamesAlbum(): void
    {
        $album = Album::create('album-1', 'Album Viejo', null);
        $this->albums->save($album);
        Request::withJsonBody(json_encode(['nombre' => 'Album Nuevo']));

        $payload = $this->capture(fn () => $this->controller->update('album-1'));

        self::assertSame('éxito', $payload['estado']);
        self::assertSame('Album Nuevo', $payload['datos']['nombre']);
    }

    public function testDestroyReturnsErrorWhenAlbumMissing(): void
    {
        $payload = $this->capture(fn () => $this->controller->destroy('missing-id'));

        self::assertSame('error', $payload['estado']);
    }

    /**
     * @return array<string, mixed>
     */
    private function capture(callable $cb): array
    {
        ob_start();
        $result = $cb();
        $contents = (string) ob_get_clean();
        $payload = \App\Shared\Http\JsonResponse::lastPayload();

        if (is_array($result)) {
            return $result;
        }
        if ($payload !== null) {
            return $payload;
        }
        return $contents !== '' ? (json_decode($contents, true) ?? []) : [];
    }
}

final class HeroRepositoryStub implements HeroRepository
{
    public function save(Hero $hero): void {}
    public function find(string $heroId): ?Hero { return null; }
    public function delete(string $heroId): void {}
    public function all(): array { return []; }
    public function byAlbum(string $albumId): array { return []; }
    public function deleteByAlbum(string $albumId): void {}
}
