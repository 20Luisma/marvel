<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Application\UseCase\DeleteHeroUseCase;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Heroes\Application\UseCase\ListHeroesUseCase;
use App\Heroes\Application\UseCase\UpdateHeroUseCase;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use App\Controllers\HeroController;
use Tests\Doubles\InMemoryAlbumRepository;
use Tests\Doubles\InMemoryHeroRepository;

final class HeroControllerTest extends TestCase
{
    private HeroController $controller;
    private InMemoryHeroRepository $heroRepository;

    protected function setUp(): void
    {
        $this->heroRepository = new InMemoryHeroRepository();
        $albumRepository = new InMemoryAlbumRepository();
        $eventBus = new InMemoryEventBus();

        $this->controller = new HeroController(
            new ListHeroesUseCase($this->heroRepository),
            new CreateHeroUseCase($this->heroRepository, $albumRepository, $eventBus),
            new UpdateHeroUseCase($this->heroRepository),
            new DeleteHeroUseCase($this->heroRepository),
            new FindHeroUseCase($this->heroRepository),
        );

        http_response_code(200);
    }

    public function test_show_hero_returns_not_found_when_id_does_not_exist(): void
    {
        $payload = $this->captureJson(fn () => $this->controller->show('missing-hero'));

        self::assertSame('error', $payload['estado']);
        self::assertSame('Héroe no encontrado.', $payload['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function captureJson(callable $callable): array
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
