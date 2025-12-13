<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Application;

use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;
use Creawebes\Rag\Application\HeroKnowledgeSyncService;
use Creawebes\Rag\Infrastructure\EmbeddingStore;
use Creawebes\Rag\Infrastructure\HeroJsonKnowledgeBase;
use PHPUnit\Framework\TestCase;

final class HeroKnowledgeSyncServiceTest extends TestCase
{
    private string $kbFile;
    private string $embeddingsFile;

    protected function setUp(): void
    {
        $this->kbFile = sys_get_temp_dir() . '/kb_' . uniqid('', true) . '.json';
        file_put_contents($this->kbFile, json_encode([], JSON_PRETTY_PRINT));

        $this->embeddingsFile = sys_get_temp_dir() . '/emb_' . uniqid('', true) . '.json';
        file_put_contents($this->embeddingsFile, json_encode([], JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        @unlink($this->kbFile);
        @unlink($this->embeddingsFile);
    }

    public function test_upsert_persists_hero_and_embeddings(): void
    {
        $knowledgeBase = new HeroJsonKnowledgeBase($this->kbFile);
        $embeddingStore = new EmbeddingStore($this->embeddingsFile);
        $embeddingClient = new class implements EmbeddingClientInterface {
            public array $texts = [];
            public function embedText(string $text): array
            {
                $this->texts[] = $text;
                return [0.1, 0.2];
            }
            public function embedDocuments(array $texts): array
            {
                return [array_map(static fn () => 0.1, $texts)];
            }
        };

        $service = new HeroKnowledgeSyncService($knowledgeBase, $embeddingStore, $embeddingClient, true, null);

        $service->upsertHero('hero-xyz', 'Nuevo Héroe', 'Contenido valiente');

        $reloaded = new HeroJsonKnowledgeBase($this->kbFile);
        $stored = $reloaded->findByIds(['hero-xyz']);

        self::assertCount(1, $stored);
        self::assertSame('Nuevo Héroe', $stored[0]['nombre']);
        self::assertSame('Contenido valiente', $stored[0]['contenido']);

        $embeddings = $embeddingStore->loadByHeroIds(['hero-xyz']);
        self::assertArrayHasKey('hero-xyz', $embeddings);
        self::assertSame([0.1, 0.2], $embeddings['hero-xyz']);
    }
}
