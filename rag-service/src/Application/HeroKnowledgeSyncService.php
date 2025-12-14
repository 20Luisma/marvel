<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application;

use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;
use Creawebes\Rag\Application\Contracts\KnowledgeBaseInterface;
use Creawebes\Rag\Infrastructure\EmbeddingStore;
use ErrorException;
use RuntimeException;
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

        $line = date('c') . ' ' . $message;
        $file = $this->logFile;
        $dir = dirname($file);

        try {
            set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            });

            if (!is_dir($dir)) {
                if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                    throw new RuntimeException('Cannot create dir: ' . $dir);
                }
            }

            $bytes = file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($bytes === false) {
                throw new RuntimeException('Cannot write log file: ' . $file);
            }
        } catch (Throwable $e) {
            error_log('[rag-service][log_write_failed] ' . $e->getMessage());
            error_log('[rag-service][log_payload] ' . $line);
        } finally {
            restore_error_handler();
        }
    }
}
