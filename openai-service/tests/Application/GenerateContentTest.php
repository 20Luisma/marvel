<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Tests\Application;

use Creawebes\OpenAI\Application\Contracts\OpenAiClientInterface;
use Creawebes\OpenAI\Application\UseCase\GenerateContent;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GenerateContentTest extends TestCase
{
    public function testReturnsContentStrippingCodeFences(): void
    {
        $client = new FakeOpenAiClient([
            'choices' => [
                [
                    'message' => [
                        'content' => "```json\n{\"title\":\"Ok\"}\n```",
                    ],
                ],
            ],
        ]);

        $useCase = new GenerateContent($client);

        $result = $useCase->handle([['role' => 'user', 'content' => 'hola']]);

        $this->assertIsArray($result);
        $this->assertSame('{"title":"Ok"}', $result['content']);
    }

    public function testFallsBackWhenClientThrows(): void
    {
        $client = new class implements OpenAiClientInterface {
            public function chat(array $messages, ?string $model = null): array
            {
                throw new RuntimeException('fallo al llamar al cliente');
            }
        };

        $useCase = new GenerateContent($client);

        $result = $useCase->handle([]);

        $this->assertIsArray($result);
        $decoded = json_decode($result['content'], true);

        $this->assertIsArray($decoded);
        $this->assertSame('No se pudo generar el cómic', $decoded['title'] ?? null);
        $this->assertSame('fallo al llamar al cliente', $decoded['summary'] ?? null);
        $this->assertSame([], $decoded['panels'] ?? null);
    }

    public function testFallsBackWhenContentMissing(): void
    {
        $client = new FakeOpenAiClient([
            'choices' => [],
        ]);

        $useCase = new GenerateContent($client);

        $result = $useCase->handle([]);

        $this->assertIsArray($result);
        $decoded = json_decode($result['content'], true);

        $this->assertIsArray($decoded);
        $this->assertSame('No se pudo generar el cómic', $decoded['title'] ?? null);
        $this->assertSame('⚠️ OpenAI devolvió un formato inesperado.', $decoded['summary'] ?? null);
    }
}

final class FakeOpenAiClient implements OpenAiClientInterface
{
    /**
     * @param array<string, mixed> $response
     */
    public function __construct(private readonly array $response)
    {
    }

    public function chat(array $messages, ?string $model = null): array
    {
        return $this->response;
    }
}
