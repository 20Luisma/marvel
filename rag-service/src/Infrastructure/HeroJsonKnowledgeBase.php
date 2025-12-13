<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure;

use Creawebes\Rag\Application\Contracts\KnowledgeBaseInterface;
use InvalidArgumentException;
use RuntimeException;

final class HeroJsonKnowledgeBase implements KnowledgeBaseInterface
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

    public function upsertHero(string $heroId, string $nombre, string $contenido): void
    {
        $normalizedId = trim($heroId);
        if ($normalizedId === '') {
            throw new InvalidArgumentException('El heroId es obligatorio para registrar el héroe en RAG.');
        }

        $entry = [
            'heroId' => $normalizedId,
            'nombre' => trim($nombre),
            'contenido' => trim($contenido),
        ];

        $this->heroes[$normalizedId] = $entry;
        $this->persist();
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

    private function persist(): void
    {
        $directory = dirname($this->storageFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio para guardar héroes en ' . $directory);
        }

        $json = json_encode(array_values($this->heroes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar los héroes para guardar.');
        }

        if (file_put_contents($this->storageFile, $json) === false) {
            throw new RuntimeException('No se pudo guardar el archivo de héroes en ' . $this->storageFile);
        }
    }
}
