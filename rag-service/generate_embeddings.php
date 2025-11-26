<?php

declare(strict_types=1);

use Creawebes\Rag\Application\Clients\OpenAiEmbeddingClient;
use Creawebes\Rag\Infrastructure\EmbeddingStore;
use Creawebes\Rag\Infrastructure\HeroJsonKnowledgeBase;

require_once __DIR__ . '/vendor/autoload.php';

$rootPath = __DIR__;

$knowledgeBase = new HeroJsonKnowledgeBase($rootPath . '/storage/knowledge/heroes.json');
$embeddingClient = new OpenAiEmbeddingClient();
$store = new EmbeddingStore($rootPath . '/storage/embeddings/heroes.json');

$heroes = $knowledgeBase->all();
if ($heroes === []) {
    echo "No hay héroes en la base de conocimiento.\n";
    exit(1);
}

$texts = [];
$heroIds = [];
foreach ($heroes as $hero) {
    $heroIds[] = $hero['heroId'];
    $texts[] = trim(($hero['nombre'] ?? '') . "\n\n" . ($hero['contenido'] ?? ''));
}

echo "Generando embeddings para " . count($texts) . " héroes...\n";
$vectors = $embeddingClient->embedDocuments($texts);

if (count($vectors) !== count($heroIds)) {
    throw new RuntimeException('El número de embeddings generados no coincide con la cantidad de héroes.');
}

$mapping = [];
foreach ($heroIds as $index => $heroId) {
    $mapping[$heroId] = $vectors[$index] ?? [];
}

$store->saveAll($mapping);

echo "Embeddings guardados en storage/embeddings/heroes.json\n";
// Nota: la generación en caliente ocurre automáticamente en HeroRetriever si faltan vectores.
