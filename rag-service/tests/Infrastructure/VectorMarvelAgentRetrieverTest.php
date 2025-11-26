<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Infrastructure;

use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;
use Creawebes\Rag\Application\Rag\MarvelAgentRetriever;
use Creawebes\Rag\Application\Rag\MarvelAgentRetrieverInterface;
use Creawebes\Rag\Infrastructure\EmbeddingStore;
use Creawebes\Rag\Infrastructure\Knowledge\MarvelAgentKnowledgeBase;
use Creawebes\Rag\Infrastructure\Retrieval\VectorMarvelAgentRetriever;
use PHPUnit\Framework\TestCase;

final class VectorMarvelAgentRetrieverTest extends TestCase
{
    public function testReturnsVectorMatchesWhenEmbeddingsExist(): void
    {
        $kbPath = __DIR__ . '/../fixtures/marvel_agent_kb.json';
        $storePath = tempnam(sys_get_temp_dir(), 'agent-emb-');
        if ($storePath === false) {
            $this->fail('No se pudo crear archivo temporal');
        }
        unlink($storePath);

        $store = new EmbeddingStore($storePath);
        $store->saveAll([
            'section-1' => [1.0, 0.0],
            'section-2' => [0.0, 1.0],
        ]);

        $kb = new MarvelAgentKnowledgeBase($kbPath);
        $fallback = new MarvelAgentRetriever($kb);
        $client = new AgentFakeEmbeddingClient([1.0, 0.0]);

        $retriever = new VectorMarvelAgentRetriever(
            $kb,
            $store,
            $client,
            $fallback,
            useEmbeddings: true,
            autoRefreshEmbeddings: false
        );

        $results = $retriever->retrieve('pregunta sobre resumen', 1);

        $this->assertCount(1, $results);
        $this->assertSame('section-1', $results[0]['id']);
    }

    public function testFallsBackWhenNoEmbeddings(): void
    {
        $kbPath = __DIR__ . '/../fixtures/marvel_agent_kb.json';
        $storePath = tempnam(sys_get_temp_dir(), 'agent-emb-');
        if ($storePath === false) {
            $this->fail('No se pudo crear archivo temporal');
        }
        unlink($storePath);

        $store = new EmbeddingStore($storePath);
        $kb = new MarvelAgentKnowledgeBase($kbPath);
        $fallback = new AgentCountingFallbackRetriever();
        $client = new AgentFakeEmbeddingClient([0.0, 0.0]);

        $retriever = new VectorMarvelAgentRetriever(
            $kb,
            $store,
            $client,
            $fallback,
            useEmbeddings: true,
            autoRefreshEmbeddings: false
        );

        $retriever->retrieve('pregunta', 2);

        $this->assertSame(1, $fallback->calls);
    }
}

final class AgentFakeEmbeddingClient implements EmbeddingClientInterface
{
    /**
     * @param array<float> $queryVector
     * @param array<int, array<float>> $batch
     */
    public function __construct(
        private readonly array $queryVector,
        private readonly array $batch = []
    ) {
    }

    public function embedText(string $text): array
    {
        return $this->queryVector;
    }

    public function embedDocuments(array $texts): array
    {
        return $this->batch;
    }
}

final class AgentCountingFallbackRetriever implements MarvelAgentRetrieverInterface
{
    public int $calls = 0;

    public function retrieve(string $question, int $limit = 3): array
    {
        $this->calls++;
        return [];
    }
}
