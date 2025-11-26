<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Application;

use Creawebes\Rag\Application\Rag\MarvelAgentRetriever;
use Creawebes\Rag\Infrastructure\Knowledge\MarvelAgentKnowledgeBase;
use PHPUnit\Framework\TestCase;

final class MarvelAgentRetrieverTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = __DIR__ . '/../fixtures/marvel_agent_kb.json';
    }

    public function testReturnsTopMatchesByLexicalOverlap(): void
    {
        $kb = new MarvelAgentKnowledgeBase($this->fixturePath);
        $retriever = new MarvelAgentRetriever($kb);

        $results = $retriever->retrieve('¿Dónde está rag-service y openai-service?', 2);

        $this->assertCount(2, $results);
        $this->assertSame('section-2', $results[0]['id']);
        $this->assertArrayHasKey('text', $results[0]);
    }

    public function testReturnsEmptyWhenNoQuestionWords(): void
    {
        $kb = new MarvelAgentKnowledgeBase($this->fixturePath);
        $retriever = new MarvelAgentRetriever($kb);

        $results = $retriever->retrieve('  ', 3);

        $this->assertSame([], $results);
    }
}
