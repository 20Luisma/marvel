<?php

declare(strict_types=1);

namespace App\Application\Comics;

use App\AI\ComicGeneratorInterface;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use InvalidArgumentException;
use RuntimeException;

/**
 * Caso de uso para generar un cómic orquestando la búsqueda de héroes y la IA.
 * 
 * Este servicio de aplicación desacopla la lógica de negocio del controlador.
 */
final class GenerateComicUseCase
{
    public function __construct(
        private readonly ComicGeneratorInterface $generator,
        private readonly FindHeroUseCase $findHero
    ) {
    }

    /**
     * @param array<int, string> $heroIds
     * @return array{
     *   story: array{title: string, summary: string, panels: array<int, array{title: string, description: string, caption: string}>}
     * }
     * @throws InvalidArgumentException Si los datos de entrada son inválidos.
     * @throws RuntimeException Si el servicio de IA falla o no está configurado.
     */
    public function execute(array $heroIds): array
    {
        if ($heroIds === []) {
            throw new InvalidArgumentException('Selecciona al menos un héroe para generar el cómic.');
        }

        if (!$this->generator->isConfigured()) {
            throw new RuntimeException('La generación con IA no está disponible en este momento.');
        }

        $heroes = [];
        foreach ($heroIds as $heroId) {
            if (!is_string($heroId) || trim($heroId) === '') {
                continue;
            }

            try {
                $hero = $this->findHero->execute($heroId);
                $heroes[] = [
                    'heroId'    => $hero['heroId'] ?? '',
                    'nombre'    => $hero['nombre'] ?? '',
                    'contenido' => $hero['contenido'] ?? '',
                    'imagen'    => $hero['imagen'] ?? '',
                ];
            } catch (InvalidArgumentException) {
                // Si un héroe individual no se encuentra, simplemente lo ignoramos
                continue;
            }
        }

        if ($heroes === []) {
            throw new InvalidArgumentException('No se encontraron héroes válidos para generar el cómic.');
        }

        return $this->generator->generateComic($heroes);
    }
}
