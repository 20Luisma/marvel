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
}
