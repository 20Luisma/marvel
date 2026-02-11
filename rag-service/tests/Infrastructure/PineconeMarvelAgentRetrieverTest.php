<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Infrastructure;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;
use Creawebes\Rag\Application\Rag\MarvelAgentRetrieverInterface;
use Creawebes\Rag\Infrastructure\Retrieval\PineconeMarvelAgentRetriever;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PineconeMarvelAgentRetrieverTest extends TestCase
{
    // ─── Escenario 1: API keys vacías → fallback inmediato ───

    public function testFallsBackWhenApiKeysAreMissing(): void
    {
        $fallback = $this->createCountingFallback([
            ['id' => 'local-1', 'title' => 'Iron Man', 'text' => 'Fallback: datos locales JSON'],
        ]);

        $embeddingClient = $this->createMock(EmbeddingClientInterface::class);

        $retriever = new PineconeMarvelAgentRetriever(
            $embeddingClient,
            $fallback,
            '',  // apiKey vacía
            ''   // indexHost vacío
        );

        $results = $retriever->retrieve('¿Quién es Iron Man?');

        $this->assertTrue($fallback->called, 'Debe usar el fallback cuando las claves están vacías.');
        $this->assertCount(1, $results);
        $this->assertSame('Fallback: datos locales JSON', $results[0]['text']);
    }

    // ─── Escenario 2: Pinecone devuelve error HTTP → fallback ───

    public function testFallsBackOnPineconeNetworkError(): void
    {
        $fallback = $this->createCountingFallback([
            ['id' => 'fallback-net', 'title' => 'Spider-Man', 'text' => 'Red local activada'],
        ]);

        $embeddingClient = $this->createMock(EmbeddingClientInterface::class);
        $embeddingClient->method('embedText')->willReturn([0.1, 0.2, 0.3]);

        // Host inválido simula fallo de red / timeout
        $retriever = new PineconeMarvelAgentRetriever(
            $embeddingClient,
            $fallback,
            'fake-api-key',
            'https://invalid-pinecone-host.xxx'
        );

        $results = $retriever->retrieve('¿Quién es Spider-Man?');

        $this->assertTrue($fallback->called, 'Debe usar el fallback cuando Pinecone no responde.');
        $this->assertCount(1, $results);
        $this->assertSame('Red local activada', $results[0]['text']);
    }

    // ─── Escenario 3: Embedding client devuelve vector vacío → fallback ───

    public function testFallsBackWhenEmbeddingClientReturnsEmptyVector(): void
    {
        $fallback = $this->createCountingFallback([
            ['id' => 'fallback-empty', 'title' => 'Thor', 'text' => 'Fallback por vector vacío'],
        ]);

        $embeddingClient = $this->createMock(EmbeddingClientInterface::class);
        $embeddingClient->method('embedText')->willReturn([]); // Vector vacío

        $retriever = new PineconeMarvelAgentRetriever(
            $embeddingClient,
            $fallback,
            'valid-key',
            'https://some-pinecone-host.pinecone.io'
        );

        $results = $retriever->retrieve('¿Quién es Thor?');

        $this->assertTrue($fallback->called, 'Debe usar el fallback cuando el embedding está vacío.');
        $this->assertSame('Fallback por vector vacío', $results[0]['text']);
    }

    // ─── Escenario 4: Embedding client lanza excepción → fallback ───

    public function testFallsBackWhenEmbeddingClientThrowsException(): void
    {
        $fallback = $this->createCountingFallback([
            ['id' => 'fallback-exc', 'title' => 'Hulk', 'text' => 'Fallback por excepción en embeddings'],
        ]);

        $embeddingClient = $this->createMock(EmbeddingClientInterface::class);
        $embeddingClient->method('embedText')
            ->willThrowException(new RuntimeException('OpenAI API timeout'));

        $retriever = new PineconeMarvelAgentRetriever(
            $embeddingClient,
            $fallback,
            'valid-key',
            'https://some-pinecone-host.pinecone.io'
        );

        $results = $retriever->retrieve('¿Es Hulk el más fuerte?');

        $this->assertTrue($fallback->called, 'Debe usar el fallback cuando el embedding client lanza excepción.');
        $this->assertSame('Fallback por excepción en embeddings', $results[0]['text']);
    }

    // ─── Escenario 5: Fallback preserva la estructura de datos esperada ───

    public function testFallbackPreservesExpectedDataStructure(): void
    {
        $expectedResults = [
            ['id' => 'hero-001', 'title' => 'Captain America', 'text' => 'Líder de los Vengadores'],
            ['id' => 'hero-002', 'title' => 'Black Widow', 'text' => 'Espía y estratega'],
        ];

        $fallback = $this->createCountingFallback($expectedResults);
        $embeddingClient = $this->createMock(EmbeddingClientInterface::class);

        // Sin claves → fallback directo
        $retriever = new PineconeMarvelAgentRetriever($embeddingClient, $fallback, '', '');
        $results = $retriever->retrieve('Compara estos dos héroes', 2);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertArrayHasKey('id', $result, 'Cada resultado debe tener un id.');
            $this->assertArrayHasKey('title', $result, 'Cada resultado debe tener un title.');
            $this->assertArrayHasKey('text', $result, 'Cada resultado debe tener un text.');
        }
        $this->assertSame('Captain America', $results[0]['title']);
        $this->assertSame('Black Widow', $results[1]['title']);
    }

    // ─── Escenario 6: Solo API key presente (sin host) → fallback ───

    public function testFallsBackWhenOnlyApiKeyProvidedWithoutHost(): void
    {
        $fallback = $this->createCountingFallback([
            ['id' => 'partial', 'title' => 'Partial', 'text' => 'Config incompleta'],
        ]);

        $embeddingClient = $this->createMock(EmbeddingClientInterface::class);

        $retriever = new PineconeMarvelAgentRetriever(
            $embeddingClient,
            $fallback,
            'valid-api-key',
            ''  // Falta el host
        );

        $results = $retriever->retrieve('Test parcial');

        $this->assertTrue($fallback->called, 'Debe usar fallback si falta el index host.');
    }

    // ─── Helper: Crea un fallback que registra si fue llamado ───

    /**
     * @param array<int, array{id: string, title: string, text: string}> $response
     */
    private function createCountingFallback(array $response): object
    {
        return new class($response) implements MarvelAgentRetrieverInterface {
            public bool $called = false;

            /** @param array<int, array{id: string, title: string, text: string}> $response */
            public function __construct(private readonly array $response)
            {
            }

            public function retrieve(string $question, int $limit = 3): array
            {
                $this->called = true;
                return $this->response;
            }
        };
    }
}
