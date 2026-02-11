<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Infrastructure;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;
use Creawebes\Rag\Application\Contracts\KnowledgeBaseInterface;
use Creawebes\Rag\Application\Contracts\RetrieverInterface;
use Creawebes\Rag\Application\Similarity\CosineSimilarity;
use Creawebes\Rag\Infrastructure\EmbeddingStore;
use Creawebes\Rag\Infrastructure\VectorHeroRetriever;
use PHPUnit\Framework\TestCase;

final class VectorHeroRetrieverTest extends TestCase
{
    public function testRanksByEmbeddingsWhenAvailable(): void
    {
        $storePath = $this->createTempPath();
        $store = new EmbeddingStore($storePath);
        $store->saveAll([
            'hero-1' => [1.0, 0.0],
            'hero-2' => [0.0, 1.0],
        ]);

        $knowledgeBase = $this->createKnowledgeBase([
            'hero-1' => ['heroId' => 'hero-1', 'nombre' => 'Volador', 'contenido' => 'Vuelo supersónico'],
            'hero-2' => ['heroId' => 'hero-2', 'nombre' => 'Sigilo', 'contenido' => 'Invisible en la noche'],
        ]);

        $fallback = new CountingFallbackRetriever();
        $client = new FakeEmbeddingClient([1.0, 0.0]);

        $retriever = new VectorHeroRetriever($knowledgeBase, $store, $client, $fallback, new CosineSimilarity(), useEmbeddings: true);
        $result = $retriever->retrieve(['hero-1', 'hero-2'], 'quiero un heroe que vuele', 2);

        $this->assertSame(['hero-1', 'hero-2'], array_column($result, 'heroId'));
        $this->assertGreaterThan($result[1]['score'], $result[0]['score']);
        $this->assertSame(0, $fallback->calls, 'No debe caer al fallback cuando hay embeddings suficientes.');
    }

    public function testFallsBackWhenNoEmbeddingsAndAutoRefreshDisabled(): void
    {
        $storePath = $this->createTempPath();
        $store = new EmbeddingStore($storePath);

        $knowledgeBase = $this->createKnowledgeBase([
            'hero-1' => ['heroId' => 'hero-1', 'nombre' => 'Volador', 'contenido' => 'Vuelo supersónico'],
            'hero-2' => ['heroId' => 'hero-2', 'nombre' => 'Sigilo', 'contenido' => 'Invisible en la noche'],
        ]);

        $fallback = new CountingFallbackRetriever([
            ['heroId' => 'fallback', 'nombre' => 'Fallback', 'contenido' => 'lexico', 'score' => 0.1],
        ]);
        $client = new FakeEmbeddingClient([0.0, 0.0]);

        $retriever = new VectorHeroRetriever($knowledgeBase, $store, $client, $fallback, new CosineSimilarity(), useEmbeddings: true, autoRefreshEmbeddings: false);
        $result = $retriever->retrieve(['hero-1', 'hero-2'], 'pregunta', 2);

        $this->assertSame([['heroId' => 'fallback', 'nombre' => 'Fallback', 'contenido' => 'lexico', 'score' => 0.1]], $result);
        $this->assertSame(1, $fallback->calls);
    }

    public function testAutoRefreshGeneratesAndPersistsMissingEmbeddings(): void
    {
        $storePath = $this->createTempPath();
        $store = new EmbeddingStore($storePath);

        $knowledgeBase = $this->createKnowledgeBase([
            'hero-1' => ['heroId' => 'hero-1', 'nombre' => 'Volador', 'contenido' => 'Vuelo supersónico'],
            'hero-2' => ['heroId' => 'hero-2', 'nombre' => 'Sigilo', 'contenido' => 'Invisible en la noche'],
        ]);

        $client = new FakeEmbeddingClient(
            queryVector: [0.0, 1.0],
            batchVectors: [
                [0.5, 0.5],
                [0.0, 1.0],
            ],
        );
        $fallback = new CountingFallbackRetriever();

        $retriever = new VectorHeroRetriever($knowledgeBase, $store, $client, $fallback, new CosineSimilarity(), useEmbeddings: true, autoRefreshEmbeddings: true);
        $result = $retriever->retrieve(['hero-1', 'hero-2'], 'prefiero sigilo', 2);

        $this->assertSame(['hero-2', 'hero-1'], array_column($result, 'heroId'));
        $this->assertSame(0, $fallback->calls);

        $saved = $store->loadAll();
        $this->assertArrayHasKey('hero-1', $saved);
        $this->assertArrayHasKey('hero-2', $saved);
    }

    public function testFallsBackWhenEmbeddingClientReturnsEmptyQueryVector(): void
    {
        $storePath = $this->createTempPath();
        $store = new EmbeddingStore($storePath);
        $store->saveAll([
            'hero-1' => [1.0, 0.0],
            'hero-2' => [0.0, 1.0],
        ]);

        $knowledgeBase = $this->createKnowledgeBase([
            'hero-1' => ['heroId' => 'hero-1', 'nombre' => 'Volador', 'contenido' => 'Vuelo supersónico'],
            'hero-2' => ['heroId' => 'hero-2', 'nombre' => 'Sigilo', 'contenido' => 'Invisible en la noche'],
        ]);

        $fallback = new CountingFallbackRetriever([
            ['heroId' => 'hero-1', 'nombre' => 'Volador', 'contenido' => 'Vuelo supersónico', 'score' => 0.5],
            ['heroId' => 'hero-2', 'nombre' => 'Sigilo', 'contenido' => 'Invisible', 'score' => 0.3],
        ]);

        // embedText devuelve vector vacío → debe activar fallback
        $client = new FakeEmbeddingClient([]);

        $retriever = new VectorHeroRetriever(
            $knowledgeBase, $store, $client, $fallback, new CosineSimilarity(),
            useEmbeddings: true
        );

        $result = $retriever->retrieve(['hero-1', 'hero-2'], 'pregunta cualquiera', 2);

        $this->assertSame(1, $fallback->calls, 'Debe usar fallback léxico cuando embedText devuelve vector vacío.');
        $this->assertCount(2, $result);
    }

    public function testFallsBackWhenEmbeddingsDisabled(): void
    {
        $storePath = $this->createTempPath();
        $store = new EmbeddingStore($storePath);

        $knowledgeBase = $this->createKnowledgeBase([
            'hero-1' => ['heroId' => 'hero-1', 'nombre' => 'Volador', 'contenido' => 'Vuelo supersónico'],
            'hero-2' => ['heroId' => 'hero-2', 'nombre' => 'Sigilo', 'contenido' => 'Invisible'],
        ]);

        $fallback = new CountingFallbackRetriever([
            ['heroId' => 'hero-1', 'nombre' => 'Volador', 'contenido' => 'Vuelo supersónico', 'score' => 0.5],
            ['heroId' => 'hero-2', 'nombre' => 'Sigilo', 'contenido' => 'Invisible', 'score' => 0.3],
        ]);

        $client = new FakeEmbeddingClient([1.0, 0.0]);

        // useEmbeddings: false → debe ir directo al fallback
        $retriever = new VectorHeroRetriever(
            $knowledgeBase, $store, $client, $fallback, new CosineSimilarity(),
            useEmbeddings: false
        );

        $result = $retriever->retrieve(['hero-1', 'hero-2'], 'pregunta', 2);

        $this->assertSame(1, $fallback->calls, 'Debe usar fallback cuando embeddings está deshabilitado.');
        $this->assertCount(2, $result);
    }

    private function createTempPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rag-embeddings-');
        if ($path === false) {
            $this->fail('No se pudo crear archivo temporal para los tests.');
        }

        // Ensure a clean file for EmbeddingStore that expects existing dir.
        unlink($path);

        return $path;
    }

    /**
     * @param array<string, array{heroId: string, nombre: string, contenido: string}> $heroes
     */
    private function createKnowledgeBase(array $heroes): KnowledgeBaseInterface
    {
        return new class($heroes) implements KnowledgeBaseInterface {
            /** @param array<string, array{heroId: string, nombre: string, contenido: string}> $heroes */
            public function __construct(private array $heroes)
            {
            }

            public function findByIds(array $heroIds): array
            {
                $result = [];
                foreach ($heroIds as $id) {
                    if (isset($this->heroes[$id])) {
                        $result[] = $this->heroes[$id];
                    }
                }

                return $result;
            }

            public function all(): array
            {
                return array_values($this->heroes);
            }

            public function upsertHero(string $heroId, string $nombre, string $contenido): void
            {
                $this->heroes[$heroId] = [
                    'heroId' => $heroId,
                    'nombre' => $nombre,
                    'contenido' => $contenido,
                ];
            }
        };
    }
}

final class FakeEmbeddingClient implements EmbeddingClientInterface
{
    /**
     * @param array<float> $queryVector
     * @param array<int, array<float>> $batchVectors
     */
    public function __construct(
        private readonly array $queryVector,
        private readonly array $batchVectors = [],
    ) {
    }

    public function embedText(string $text): array
    {
        return $this->queryVector;
    }

    public function embedDocuments(array $texts): array
    {
        return $this->batchVectors;
    }
}

final class CountingFallbackRetriever implements RetrieverInterface
{
    public int $calls = 0;

    /**
     * @param array<int, array{heroId: string, nombre: string, contenido: string, score: float}> $response
     */
    public function __construct(private readonly array $response = [])
    {
    }

    public function retrieve(array $heroIds, string $question, int $limit = 5): array
    {
        $this->calls++;
        return $this->response;
    }
}
