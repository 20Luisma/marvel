<?php

declare(strict_types=1);

use Creawebes\Rag\Application\Clients\OpenAiEmbeddingClient;
use Creawebes\Rag\Infrastructure\EmbeddingStore;
use Creawebes\Rag\Infrastructure\Knowledge\MarvelAgentKnowledgeBase;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Generate embeddings for Marvel Agent KB.
 * Usage: php bin/generate_agent_embeddings.php
 */

$rootPath = dirname(__DIR__);
$kbFile = $rootPath . '/storage/marvel_agent_kb.json';
$embeddingFile = $rootPath . '/storage/marvel_agent_embeddings.json';

$knowledgeBase = new MarvelAgentKnowledgeBase($kbFile);
$entries = $knowledgeBase->all();

if ($entries === []) {
    echo "No hay entradas en la KB. Genera primero la KB con bin/build_marvel_agent_kb.php\n";
    exit(1);
}

$texts = [];
$ids = [];
foreach ($entries as $entry) {
    $ids[] = $entry['id'];
    $texts[] = trim($entry['title'] . "\n\n" . $entry['text']);
}

echo "Generando embeddings para " . count($texts) . " entradas...\n";

$client = new OpenAiEmbeddingClient();
$vectors = $client->embedDocuments($texts);

if (count($vectors) !== count($ids)) {
    echo "El número de embeddings no coincide con las entradas.\n";
    exit(1);
}

$mapping = [];
foreach ($ids as $index => $id) {
    $mapping[$id] = $vectors[$index] ?? [];
}

$store = new EmbeddingStore($embeddingFile);
$store->saveAll($mapping);

echo "Embeddings guardados en {$embeddingFile}\n";
