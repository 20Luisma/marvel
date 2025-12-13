<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Application;

use Creawebes\Rag\Application\Contracts\KnowledgeBaseInterface;
use Creawebes\Rag\Application\Contracts\LlmClientInterface;
use Creawebes\Rag\Application\Contracts\RetrieverInterface;
use Creawebes\Rag\Application\HeroRagService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HeroRagServiceTest extends TestCase
{
    public function testCompareReturnsAnswerAndContextsWithPromptData(): void
    {
        $heroes = [
            'hero-1' => [
                'heroId' => 'hero-1',
                'nombre' => 'Hero One',
                'contenido' => 'Fuerza y vuelo',
            ],
            'hero-2' => [
                'heroId' => 'hero-2',
                'nombre' => 'Hero Two',
                'contenido' => 'Sigilo experto',
            ],
        ];

        $contexts = [
            [
                'heroId' => 'hero-1',
                'nombre' => 'Hero One',
                'contenido' => 'Fuerza y vuelo',
                'score' => 0.9,
            ],
            [
                'heroId' => 'hero-2',
                'nombre' => 'Hero Two',
                'contenido' => 'Sigilo experto',
                'score' => 0.4,
            ],
        ];

        $knowledgeBase = $this->createKnowledgeBase($heroes);
        $retriever = new FakeRetriever($contexts);
        $llmClient = new FakeLlmClient('respuesta de prueba');

        $service = new HeroRagService($knowledgeBase, $retriever, $llmClient);
        $result = $service->compare(['hero-1', 'hero-2'], 'Quien es mejor?');

        $this->assertSame('respuesta de prueba', $result['answer']);
        $this->assertSame($contexts, $result['contexts']);
        $this->assertSame(['hero-1', 'hero-2'], $result['heroIds']);
        $this->assertStringContainsString('Hero One', (string) $llmClient->lastPrompt());
        $this->assertStringContainsString('Hero Two', (string) $llmClient->lastPrompt());
        $this->assertStringContainsString('Quien es mejor?', (string) $llmClient->lastPrompt());
    }

    #[DataProvider('invalidHeroIds')]
    public function testCompareRequiresExactlyTwoHeroes(array $heroIds): void
    {
        $knowledgeBase = $this->createKnowledgeBase([]);
        $retriever = new FakeRetriever([]);
        $llmClient = new FakeLlmClient('respuesta de prueba');

        $service = new HeroRagService($knowledgeBase, $retriever, $llmClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Selecciona 2 héroes primero.');

        $service->compare($heroIds, 'pregunta');
    }

    /**
     * @return array<string, array{0: array<int, string>}>
     */
    public static function invalidHeroIds(): array
    {
        return [
            'none' => [[]],
            'one' => [['hero-1']],
            'three' => [['hero-1', 'hero-2', 'hero-3']],
        ];
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

final class FakeRetriever implements RetrieverInterface
{
    /**
     * @param array<int, array{heroId: string, nombre: string, contenido: string, score: float}> $contexts
     */
    public function __construct(private readonly array $contexts)
    {
    }

    public function retrieve(array $heroIds, string $question, int $limit = 5): array
    {
        return $this->contexts;
    }
}

final class FakeLlmClient implements LlmClientInterface
{
    private ?string $lastPrompt = null;

    public function __construct(private readonly string $answer)
    {
    }

    public function ask(string $prompt): string
    {
        $this->lastPrompt = $prompt;

        return $this->answer;
    }

    public function lastPrompt(): ?string
    {
        return $this->lastPrompt;
    }
}
