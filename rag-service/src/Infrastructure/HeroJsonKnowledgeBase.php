<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure;

use RuntimeException;

final class HeroJsonKnowledgeBase
{
    /** @var array<string, array{heroId: string, nombre: string, contenido: string}> */
    private array $heroes = [];

    public function __construct(private readonly string $storageFile)
    {
        $this->heroes = $this->loadHeroes();
    }

    /**
     * @return array<int, array{heroId: string, nombre: string, contenido: string}>
     */
    public function findByIds(array $heroIds): array
    {
        $result = [];
        foreach ($heroIds as $heroId) {
            if (!is_string($heroId) || trim($heroId) === '') {
                continue;
            }
            $heroId = trim($heroId);
            if (isset($this->heroes[$heroId])) {
                $result[] = $this->heroes[$heroId];
            }
        }

        return $result;
    }

    /**
     * @return array<int, array{heroId: string, nombre: string, contenido: string}>
     */
    public function all(): array
    {
        return array_values($this->heroes);
    }

    /**
     * @return array<string, array{heroId: string, nombre: string, contenido: string}>
     */
    private function loadHeroes(): array
    {
        if (!is_file($this->storageFile)) {
            throw new RuntimeException('No se encontró el archivo de conocimiento de héroes en ' . $this->storageFile);
        }

        $contents = file_get_contents($this->storageFile);
        if ($contents === false) {
            throw new RuntimeException('No se pudo leer el archivo de héroes.');
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('El archivo de héroes tiene un formato inválido.');
        }

        $heroes = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $heroId = isset($entry['heroId']) ? (string) $entry['heroId'] : '';
            if ($heroId === '') {
                continue;
            }

            $heroes[$heroId] = [
                'heroId' => $heroId,
                'nombre' => isset($entry['nombre']) ? (string) $entry['nombre'] : '',
                'contenido' => isset($entry['contenido']) ? (string) $entry['contenido'] : '',
            ];
        }

        return $heroes;
    }
}
