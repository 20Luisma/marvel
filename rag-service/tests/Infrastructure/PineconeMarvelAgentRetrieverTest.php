<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Infrastructure;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;
use Creawebes\Rag\Application\Rag\MarvelAgentRetrieverInterface;
use Creawebes\Rag\Infrastructure\Retrieval\PineconeMarvelAgentRetriever;
use PHPUnit\Framework\TestCase;

final class PineconeMarvelAgentRetrieverTest extends TestCase
{
    public function testFallsBackWhenApiKeysAreMissing(): void
    {
        $fallback = new class implements MarvelAgentRetrieverInterface {
            public bool $called = false;
            public function retrieve(string $question, int $limit = 3): array {
                $this->called = true;
                return [['id' => 'fake', 'title' => 'Fake', 'text' => 'Fallback worked']];
            }
        };

        $embeddingClient = $this->createMock(EmbeddingClientInterface::class);
        
        // SUT con APIs vacías para forzar fallback inmediato
        $retriever = new PineconeMarvelAgentRetriever(
            $embeddingClient,
            $fallback,
            '', // apiKey
            ''  // indexHost
        );

        $results = $retriever->retrieve('¿Quién es Iron Man?');

        $this->assertTrue($fallback->called);
        $this->assertCount(1, $results);
        $this->assertSame('Fallback worked', $results[0]['text']);
    }

    public function testFallsBackOnError(): void
    {
        $fallback = new class implements MarvelAgentRetrieverInterface {
            public bool $called = false;
            public function retrieve(string $question, int $limit = 3): array {
                $this->called = true;
                return [['id' => 'error-fallback', 'title' => 'Error', 'text' => 'Error fallback worked']];
            }
        };

        $embeddingClient = $this->createMock(EmbeddingClientInterface::class);
        $embeddingClient->method('embedText')->willReturn([0.1, 0.2]);

        // SUT con APIs que darán error (URLs inválidas o timeouts)
        $retriever = new PineconeMarvelAgentRetriever(
            $embeddingClient,
            $fallback,
            'key-xyz',
            'https://invalid-host-for-testing.xxx'
        );

        // Debería capturar el error y usar el fallback
        $results = $retriever->retrieve('Cualquier cosa');

        $this->assertTrue($fallback->called);
        $this->assertCount(1, $results);
    }
}
