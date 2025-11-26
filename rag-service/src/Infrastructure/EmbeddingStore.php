<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure;

use RuntimeException;

final class EmbeddingStore
{
    public function __construct(private readonly string $storageFile)
    {
    }

    /**
     * @param array<string|int, array<float>> $heroIdToVector
     */
    public function saveAll(array $heroIdToVector): void
    {
        $directory = dirname($this->storageFile);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('No se pudo crear el directorio de embeddings en ' . $directory);
            }
        }

        $json = json_encode($heroIdToVector, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar los embeddings para guardar.');
        }

        if (file_put_contents($this->storageFile, $json) === false) {
            throw new RuntimeException('No se pudo guardar el archivo de embeddings en ' . $this->storageFile);
        }
    }

    /**
     * @return array<string, array<float>>
     */
    public function loadAll(): array
    {
        if (!is_file($this->storageFile)) {
            return [];
        }

        $contents = file_get_contents($this->storageFile);
        if ($contents === false) {
            throw new RuntimeException('No se pudo leer el archivo de embeddings en ' . $this->storageFile);
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $heroId => $vector) {
            if (!is_string($heroId) || !is_array($vector)) {
                continue;
            }
            $result[$heroId] = array_map('floatval', $vector);
        }

        return $result;
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<float>>
     */
    public function loadByHeroIds(array $ids): array
    {
        $all = $this->loadAll();
        if ($all === []) {
            return [];
        }

        $result = [];
        foreach ($ids as $id) {
            if (is_string($id) && isset($all[$id])) {
                $result[$id] = $all[$id];
            }
        }

        return $result;
    }

    /**
     * @param string|int $heroId
     * @param array<int|float> $vector
     */
    public function saveOne(string|int $heroId, array $vector): void
    {
        $all = $this->loadAll();
        $all[(string) $heroId] = array_map('floatval', $vector);
        $this->saveAll($all);
    }
}
