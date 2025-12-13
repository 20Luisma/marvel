<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application;

use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;
use Creawebes\Rag\Application\Contracts\KnowledgeBaseInterface;
use Creawebes\Rag\Infrastructure\EmbeddingStore;
use Throwable;

final class HeroKnowledgeSyncService
{
    public function __construct(
        private readonly KnowledgeBaseInterface $knowledgeBase,
        private readonly ?EmbeddingStore $embeddingStore = null,
        private readonly ?EmbeddingClientInterface $embeddingClient = null,
        private readonly bool $useEmbeddings = false,
        private readonly ?string $logFile = null,
    ) {
    }

    public function upsertHero(string $heroId, string $nombre, string $contenido): void
    {
        $this->knowledgeBase->upsertHero($heroId, $nombre, $contenido);

        if ($this->useEmbeddings === false || $this->embeddingStore === null || $this->embeddingClient === null) {
            return;
        }

        try {
            $text = trim($nombre . "\n\n" . $contenido);
            $vector = $this->embeddingClient->embedText($text);
            if ($vector !== []) {
                $this->embeddingStore->saveOne($heroId, $vector);
            }
        } catch (Throwable $exception) {
            $this->log('No se pudo generar embeddings para el héroe ' . $heroId . ': ' . $exception->getMessage());
        }
    }

    private function log(string $message): void
    {
        if ($this->logFile === null || $this->logFile === '') {
            return;
        }

        $line = date('c') . ' ' . $message . PHP_EOL;
        @file_put_contents($this->logFile, $line, FILE_APPEND);
    }
}
