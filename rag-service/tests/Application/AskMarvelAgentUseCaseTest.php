<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Application;

use Creawebes\Rag\Application\Contracts\LlmClientInterface;
use Creawebes\Rag\Application\UseCase\AskMarvelAgentUseCase;
use Creawebes\Rag\Application\Rag\MarvelAgentRetrieverInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AskMarvelAgentUseCaseTest extends TestCase
{
    public function testReturnsAnswerAndContexts(): void
    {
        $retriever = new class implements MarvelAgentRetrieverInterface {
            public function retrieve(string $question, int $limit = 3): array
            {
                return [
                    ['id' => 'section-1', 'title' => 'Resumen', 'text' => 'KB texto'],
                    ['id' => 'section-2', 'title' => 'Infra', 'text' => 'Infra texto'],
                ];
            }
        };

        $llm = new class implements LlmClientInterface {
            public function ask(string $prompt): string
            {
                $this->prompt = $prompt;
                return 'respuesta del agente';
            }

            public ?string $prompt = null;
        };

        $useCase = new AskMarvelAgentUseCase($retriever, $llm);
        $result = $useCase->ask('¿Dónde corre el RAG?');

        $this->assertSame('respuesta del agente', $result['answer']);
        $this->assertCount(2, $result['contexts']);
        $this->assertSame('section-1', $result['contexts'][0]['id']);
    }

    public function testThrowsOnEmptyQuestion(): void
    {
        $retriever = new class implements MarvelAgentRetrieverInterface {
            public function retrieve(string $question, int $limit = 3): array
            {
                return [];
            }
        };

        $llm = new class implements LlmClientInterface {
            public function ask(string $prompt): string
            {
                return '';
            }
        };

        $useCase = new AskMarvelAgentUseCase($retriever, $llm);

        $this->expectException(InvalidArgumentException::class);
        $useCase->ask('   ');
    }
}
