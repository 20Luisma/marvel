<?php

declare(strict_types=1);

namespace App\Controllers;

use App\AI\OpenAIComicGenerator;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Shared\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;
use App\Controllers\Http\Request;
use Throwable;

final class ComicController
{
    public function __construct(
        private readonly OpenAIComicGenerator $generator,
        private readonly FindHeroUseCase $findHero,
    ) {
    }

    public function generate(): void
    {
        $payload = Request::jsonBody();
        $heroIds = $payload['heroIds'] ?? [];

        if (!is_array($heroIds) || $heroIds === []) {
            JsonResponse::error('Selecciona al menos un héroe para generar el cómic.', 422);
            return;
        }

        if (!$this->generator->isConfigured()) {
            JsonResponse::error('La generación con IA no está disponible. Levanta el microservicio en http://localhost:8081.', 503);
            return;
        }

        $heroes = [];
        foreach ($heroIds as $heroId) {
            if (!is_string($heroId) || trim($heroId) === '') {
                continue;
            }

            try {
                $hero = $this->findHero->execute($heroId);
            } catch (InvalidArgumentException) {
                continue;
            }

            $heroes[] = [
                'heroId' => $hero['heroId'] ?? '',
                'nombre' => $hero['nombre'] ?? '',
                'contenido' => $hero['contenido'] ?? '',
                'imagen' => $hero['imagen'] ?? '',
            ];
        }

        if ($heroes === []) {
            JsonResponse::error('No se encontraron héroes válidos para generar el cómic.', 404);
            return;
        }

        try {
            $result = $this->generator->generateComic($heroes);
            JsonResponse::success($result, 201);
        } catch (InvalidArgumentException $exception) {
            JsonResponse::error($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            JsonResponse::error($exception->getMessage(), 502);
        } catch (Throwable $exception) {
            JsonResponse::error('No se pudo generar el cómic con IA: ' . $exception->getMessage(), 502);
        }

        // TODO: mover la orquestación de generación a src/Application/Comics/GenerateComicService.
    }
}
