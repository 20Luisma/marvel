<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure\Knowledge;

use RuntimeException;

final class MarvelAgentKnowledgeBase
{
    public function __construct(private readonly string $kbPath)
    {
    }

    /**
     * @return array<int, array{id: string, title: string, text: string}>
     */
    public function all(): array
    {
        if (!is_file($this->kbPath)) {
            throw new RuntimeException('No se encontró el archivo de KB en ' . $this->kbPath);
        }

        $raw = file_get_contents($this->kbPath);
        if ($raw === false) {
            throw new RuntimeException('No se pudo leer el archivo de KB en ' . $this->kbPath);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = isset($entry['id']) ? (string) $entry['id'] : '';
            $title = isset($entry['title']) ? (string) $entry['title'] : '';
            $text = isset($entry['text']) ? (string) $entry['text'] : '';

            if ($id === '' || $title === '' || $text === '') {
                continue;
            }

            $result[] = [
                'id' => $id,
                'title' => $title,
                'text' => $text,
            ];
        }

        return $result;
    }
}
