<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Application\Comics\GenerateComicUseCase;
use App\Shared\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;
use App\Controllers\Http\Request;
use Throwable;

/**
 * Controlador para la generación de cómics.
 * 
 * Ahora es mucho más limpio (Skinny Controller) porque la lógica pesada
 * se ha movido a la capa de Aplicación (GenerateComicUseCase).
 */
final class ComicController
{
    public function __construct(
        private readonly GenerateComicUseCase $generateComic
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

        try {
            $result = $this->generateComic->execute($heroIds);
            JsonResponse::success($result, 201);
        } catch (InvalidArgumentException $exception) {
            JsonResponse::error($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            // El Use Case lanza RuntimeException para errores de configuración o disponibilidad
            JsonResponse::error($exception->getMessage(), 502);
        } catch (Throwable $exception) {
            JsonResponse::error('Error inesperado: ' . $exception->getMessage(), 500);
        }
    }
}
