<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Infrastructure;

use Creawebes\Rag\Infrastructure\Knowledge\MarvelAgentKnowledgeBase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MarvelAgentKnowledgeBaseTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = __DIR__ . '/../fixtures/marvel_agent_kb.json';
    }

    public function testLoadsAllEntries(): void
    {
        $kb = new MarvelAgentKnowledgeBase($this->fixturePath);
        $all = $kb->all();

        $this->assertCount(3, $all);
        $this->assertSame('section-1', $all[0]['id']);
        $this->assertSame('Resumen', $all[0]['title']);
        $this->assertStringContainsString('Clean Marvel Album', $all[0]['text']);
    }

    public function testThrowsWhenFileMissing(): void
    {
        $kb = new MarvelAgentKnowledgeBase(__DIR__ . '/missing.json');

        $this->expectException(RuntimeException::class);
        $kb->all();
    }
}
