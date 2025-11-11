<?php

declare(strict_types=1);

namespace Src\Controllers;

use App\Heroes\Application\DTO\CreateHeroRequest;
use App\Heroes\Application\DTO\UpdateHeroRequest;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Application\UseCase\DeleteHeroUseCase;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Heroes\Application\UseCase\ListHeroesUseCase;
use App\Heroes\Application\UseCase\UpdateHeroUseCase;
use App\Shared\Http\JsonResponse;
use InvalidArgumentException;
use Src\Controllers\Http\Request;

final class HeroController
{
    public function __construct(
        private readonly ListHeroesUseCase $listHeroes,
        private readonly CreateHeroUseCase $createHero,
        private readonly UpdateHeroUseCase $updateHero,
        private readonly DeleteHeroUseCase $deleteHero,
        private readonly FindHeroUseCase $findHero,
    ) {
    }

    public function index(): void
    {
        $data = $this->listHeroes->execute();
        JsonResponse::success($data);
    }

    public function listByAlbum(string $albumId): void
    {
        $data = $this->listHeroes->execute($albumId);
        JsonResponse::success($data);
    }

    public function store(string $albumId): void
    {
        $payload = Request::jsonBody();
        $nombre = trim((string)($payload['nombre'] ?? ''));
        $contenido = (string)($payload['contenido'] ?? '');
        $imagen = trim((string)($payload['imagen'] ?? ''));

        if ($nombre === '' || $imagen === '') {
            JsonResponse::error('Los campos nombre e imagen son obligatorios.', 422);
            return;
        }

        $response = $this->createHero->execute(new CreateHeroRequest($albumId, $nombre, $contenido, $imagen));
        JsonResponse::success($response->toArray(), 201);
    }

    public function show(string $heroId): void
    {
        try {
            $data = $this->findHero->execute($heroId);
            JsonResponse::success($data);
        } catch (InvalidArgumentException $exception) {
            JsonResponse::error($exception->getMessage(), 404);
        }
    }

    public function update(string $heroId): void
    {
        $payload = Request::jsonBody();
        $nombre = array_key_exists('nombre', $payload) ? (string)$payload['nombre'] : null;
        $contenido = array_key_exists('contenido', $payload) ? (string)$payload['contenido'] : null;
        $imagen = array_key_exists('imagen', $payload) ? (string)$payload['imagen'] : null;

        $response = $this->updateHero->execute(new UpdateHeroRequest($heroId, $nombre, $contenido, $imagen));
        JsonResponse::success($response->toArray());
    }

    public function destroy(string $heroId): void
    {
        try {
            $this->deleteHero->execute($heroId);
            JsonResponse::success(['message' => 'Héroe eliminado.']);
        } catch (InvalidArgumentException $exception) {
            JsonResponse::error($exception->getMessage(), 404);
        }
    }
}
