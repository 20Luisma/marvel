<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Application;

use Creawebes\Rag\Application\Contracts\KnowledgeBaseInterface;
use Creawebes\Rag\Application\HeroRetriever;
use Creawebes\Rag\Application\Similarity\CosineSimilarity;
use PHPUnit\Framework\TestCase;

final class HeroRetrieverTest extends TestCase
{
    public function testRankOrdersDocumentsByDescendingScore(): void
    {
        $knowledgeBase = $this->createKnowledgeBase([
            'hero-1' => [
                'heroId' => 'hero-1',
                'nombre' => 'Fuerza Voladora',
                'contenido' => 'Puede volar con gran fuerza y resistencia',
            ],
            'hero-2' => [
                'heroId' => 'hero-2',
                'nombre' => 'Especialista en sigilo',
                'contenido' => 'Entrenado en sigilo y espionaje',
            ],
        ]);

        $retriever = new HeroRetriever($knowledgeBase, new CosineSimilarity());

        $result = $retriever->retrieve(['hero-1', 'hero-2'], 'fuerza volar');

        $this->assertCount(2, $result);
        $this->assertSame('hero-1', $result[0]['heroId']);
        $this->assertSame('hero-2', $result[1]['heroId']);
        $this->assertGreaterThan($result[1]['score'], $result[0]['score']);
    }

    public function testRespectsLimitWhenRanking(): void
    {
        $knowledgeBase = $this->createKnowledgeBase([
            'hero-1' => [
                'heroId' => 'hero-1',
                'nombre' => 'Fuerza pura',
                'contenido' => 'Se centra en fuerza',
            ],
            'hero-2' => [
                'heroId' => 'hero-2',
                'nombre' => 'Fuerza y velocidad',
                'contenido' => 'Combina fuerza y velocidad',
            ],
            'hero-3' => [
                'heroId' => 'hero-3',
                'nombre' => 'Sigilo total',
                'contenido' => 'Domina el sigilo',
            ],
        ]);

        $retriever = new HeroRetriever($knowledgeBase, new CosineSimilarity());

        $result = $retriever->retrieve(['hero-1', 'hero-2', 'hero-3'], 'fuerza velocidad', 2);

        $this->assertCount(2, $result);
        $this->assertSame(['hero-2', 'hero-1'], array_column($result, 'heroId'));
    }

    public function testHandlesEmptyOrShortQuestionsGracefully(): void
    {
        $knowledgeBase = $this->createKnowledgeBase([
            'hero-1' => [
                'heroId' => 'hero-1',
                'nombre' => 'Heroe Uno',
                'contenido' => 'Descripcion breve',
            ],
            'hero-2' => [
                'heroId' => 'hero-2',
                'nombre' => 'Heroe Dos',
                'contenido' => 'Otra descripcion breve',
            ],
        ]);

        $retriever = new HeroRetriever($knowledgeBase, new CosineSimilarity());

        $resultWithEmpty = $retriever->retrieve(['hero-1', 'hero-2'], '');
        $resultWithShort = $retriever->retrieve(['hero-1', 'hero-2'], 'ok');

        $this->assertCount(2, $resultWithEmpty);
        $this->assertCount(2, $resultWithShort);

        foreach ($resultWithEmpty as $context) {
            $this->assertArrayHasKey('score', $context);
            $this->assertIsFloat($context['score']);
        }

        foreach ($resultWithShort as $context) {
            $this->assertArrayHasKey('score', $context);
            $this->assertIsFloat($context['score']);
        }
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
                foreach ($heroIds as $heroId) {
                    if (isset($this->heroes[$heroId])) {
                        $result[] = $this->heroes[$heroId];
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
