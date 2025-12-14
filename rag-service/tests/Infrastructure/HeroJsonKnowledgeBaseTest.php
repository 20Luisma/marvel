<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Infrastructure;

use Creawebes\Rag\Infrastructure\HeroJsonKnowledgeBase;
use PHPUnit\Framework\TestCase;

final class HeroJsonKnowledgeBaseTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__ . '/../fixtures/heroes.json';

    public function testLoadsHeroesFromFixture(): void
    {
        $knowledgeBase = new HeroJsonKnowledgeBase(self::FIXTURE_PATH);

        $heroes = $knowledgeBase->all();

        $this->assertCount(3, $heroes);
        $this->assertSame('ironman', $heroes[0]['heroId']);
        $this->assertSame('Captain Valor', $heroes[1]['nombre']);
    }

    public function testFindByIdsReturnsOnlyRequestedHeroes(): void
    {
        $knowledgeBase = new HeroJsonKnowledgeBase(self::FIXTURE_PATH);

        $result = $knowledgeBase->findByIds(['spider', 'desconocido', 'captain']);

        $this->assertCount(2, $result);
        $this->assertSame(['spider', 'captain'], array_column($result, 'heroId'));
        $this->assertSame('Spider Hero', $result[0]['nombre']);
        $this->assertSame('Captain Valor', $result[1]['nombre']);
    }

    public function testUpsertHeroPersistsToDisk(): void
    {
        $tempFile = sys_get_temp_dir() . '/heroes_' . uniqid('', true) . '.json';
        file_put_contents($tempFile, json_encode([], JSON_PRETTY_PRINT));

        $knowledgeBase = new HeroJsonKnowledgeBase($tempFile);
        $knowledgeBase->upsertHero('hero-123', 'Nuevo', 'Contenido');

        $reloaded = new HeroJsonKnowledgeBase($tempFile);
        $heroes = $reloaded->findByIds(['hero-123']);

        $this->assertCount(1, $heroes);
        $this->assertSame('Nuevo', $heroes[0]['nombre']);
        $this->assertSame('Contenido', $heroes[0]['contenido']);

        if (is_file($tempFile)) {
            unlink($tempFile);
        }
    }
}
