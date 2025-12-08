<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Heroes\Application\DTO\HeroResponse;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Application\UseCase\DeleteHeroUseCase;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Heroes\Application\UseCase\ListHeroesUseCase;
use App\Heroes\Application\UseCase\UpdateHeroUseCase;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use App\Controllers\HeroController;
use App\Controllers\Http\Request;
use Tests\Doubles\InMemoryAlbumRepository;
use Tests\Doubles\InMemoryHeroRepository;

final class HeroControllerExtendedTest extends TestCase
{
    private HeroController $controller;
    private InMemoryHeroRepository $heroRepository;
    private InMemoryAlbumRepository $albumRepository;

    protected function setUp(): void
    {
        $this->heroRepository = new InMemoryHeroRepository();
        $this->albumRepository = new InMemoryAlbumRepository();
        $eventBus = new InMemoryEventBus();

        $this->controller = new HeroController(
            new ListHeroesUseCase($this->heroRepository),
            new CreateHeroUseCase($this->heroRepository, $this->albumRepository, $eventBus),
            new UpdateHeroUseCase($this->heroRepository),
            new DeleteHeroUseCase($this->heroRepository),
            new FindHeroUseCase($this->heroRepository),
        );

        http_response_code(200);
    }

    public function test_index_returns_empty_list_when_no_heroes(): void
    {
        $payload = $this->capturePayload(fn () => $this->controller->index());

        self::assertSame('éxito', $payload['estado']);
        self::assertIsArray($payload['datos']);
    }

    public function test_list_by_album_returns_empty_list_for_missing_album(): void
    {
        $payload = $this->capturePayload(fn () => $this->controller->listByAlbum('nonexistent-album'));

        self::assertSame('éxito', $payload['estado']);
        self::assertIsArray($payload['datos']);
        self::assertEmpty($payload['datos']);
    }

    public function test_store_returns_error_when_nombre_is_empty(): void
    {
        Request::withJsonBody(json_encode(['nombre' => '', 'imagen' => 'hero.jpg']));

        $payload = $this->capturePayload(fn () => $this->controller->store('album-1'));

        self::assertSame('error', $payload['estado']);
        self::assertStringContainsString('obligatorios', $payload['message']);
    }

    public function test_store_returns_error_when_imagen_is_empty(): void
    {
        Request::withJsonBody(json_encode(['nombre' => 'Spider-Man', 'imagen' => '']));

        $payload = $this->capturePayload(fn () => $this->controller->store('album-1'));

        self::assertSame('error', $payload['estado']);
        self::assertStringContainsString('obligatorios', $payload['message']);
    }

    public function test_store_creates_hero_with_valid_data(): void
    {
        // First create an album
        $album = \App\Albums\Domain\Entity\Album::create('album-1', 'My Album', null);
        $this->albumRepository->save($album);

        Request::withJsonBody(json_encode([
            'nombre' => 'Spider-Man',
            'contenido' => 'Peter Parker',
            'imagen' => 'spiderman.jpg',
        ]));

        $payload = $this->capturePayload(fn () => $this->controller->store('album-1'));

        self::assertSame('éxito', $payload['estado']);
        self::assertArrayHasKey('heroId', $payload['datos']);
        self::assertSame('Spider-Man', $payload['datos']['nombre']);
    }

    public function test_update_returns_updated_hero(): void
    {
        // Create a hero first
        $hero = \App\Heroes\Domain\Entity\Hero::create(
            'hero-1',
            'album-1',
            'Iron Man',
            'Tony Stark',
            'ironman.jpg'
        );
        $this->heroRepository->save($hero);

        Request::withJsonBody(json_encode([
            'nombre' => 'Iron Man Updated',
        ]));

        $payload = $this->capturePayload(fn () => $this->controller->update('hero-1'));

        self::assertSame('éxito', $payload['estado']);
        self::assertSame('Iron Man Updated', $payload['datos']['nombre']);
    }

    public function test_destroy_removes_hero(): void
    {
        // Create a hero first
        $hero = \App\Heroes\Domain\Entity\Hero::create(
            'hero-2',
            'album-1',
            'Thor',
            'God of Thunder',
            'thor.jpg'
        );
        $this->heroRepository->save($hero);

        $payload = $this->capturePayload(fn () => $this->controller->destroy('hero-2'));

        self::assertSame('éxito', $payload['estado']);
        self::assertStringContainsString('eliminado', $payload['datos']['message']);
    }

    public function test_destroy_returns_error_for_nonexistent_hero(): void
    {
        $payload = $this->capturePayload(fn () => $this->controller->destroy('nonexistent-hero'));

        self::assertSame('error', $payload['estado']);
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
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        }

        return [];
    }
}
