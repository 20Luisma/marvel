<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Tests\Application;

use Creawebes\OpenAI\Application\Contracts\OpenAiClientInterface;
use Creawebes\OpenAI\Application\UseCase\GenerateContent;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Extended tests for the GenerateContent use case.
 *
 * Covers additional edge cases beyond the basic test file:
 * - Empty string content from API
 * - Content without code fences
 * - Null/missing choices structure
 * - Usage and model propagation
 * - Multiple exception types in fallback
 */
final class GenerateContentExtendedTest extends TestCase
{
    public function testReturnsContentWithoutCodeFencesUnchanged(): void
    {
        $rawContent = '{"title":"Direct","summary":"No fences","panels":[]}';
        $client = $this->fakeClient([
            'choices' => [
                ['message' => ['content' => $rawContent]],
            ],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 10],
            'model' => 'gpt-4o-mini',
        ]);

        $useCase = new GenerateContent($client);
        $result = $useCase->handle([['role' => 'user', 'content' => 'test']]);

        $this->assertSame($rawContent, $result['content']);
    }

    public function testPropagatesUsageData(): void
    {
        $usage = ['prompt_tokens' => 42, 'completion_tokens' => 58, 'total_tokens' => 100];
        $client = $this->fakeClient([
            'choices' => [
                ['message' => ['content' => 'test content']],
            ],
            'usage' => $usage,
            'model' => 'gpt-4o',
        ]);

        $useCase = new GenerateContent($client);
        $result = $useCase->handle([]);

        $this->assertSame($usage, $result['usage']);
        $this->assertSame('gpt-4o', $result['model']);
    }

    public function testFallbackWhenContentIsEmptyString(): void
    {
        $client = $this->fakeClient([
            'choices' => [
                ['message' => ['content' => '   ']],
            ],
        ]);

        $useCase = new GenerateContent($client);
        $result = $useCase->handle([]);

        // Empty/whitespace content triggers fallback
        $this->assertNull($result['usage']);
        $this->assertNull($result['model']);
        $decoded = json_decode($result['content'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('No se pudo generar el cómic', $decoded['title'] ?? null);
    }

    public function testFallbackWhenChoicesIsNull(): void
    {
        $client = $this->fakeClient([
            'data' => 'something-unexpected',
        ]);

        $useCase = new GenerateContent($client);
        $result = $useCase->handle([]);

        $decoded = json_decode($result['content'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('No se pudo generar el cómic', $decoded['title'] ?? null);
    }

    public function testFallbackWhenMessageKeyMissing(): void
    {
        $client = $this->fakeClient([
            'choices' => [
                ['index' => 0, 'finish_reason' => 'stop'],
            ],
        ]);

        $useCase = new GenerateContent($client);
        $result = $useCase->handle([]);

        $decoded = json_decode($result['content'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('No se pudo generar el cómic', $decoded['title'] ?? null);
    }

    public function testFallbackOnInvalidArgumentException(): void
    {
        $client = new class implements OpenAiClientInterface {
            public function chat(array $messages, ?string $model = null): array
            {
                throw new \InvalidArgumentException('Invalid argument provided');
            }
        };

        $useCase = new GenerateContent($client);
        $result = $useCase->handle([]);

        $decoded = json_decode($result['content'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('Invalid argument provided', $decoded['summary'] ?? null);
    }

    public function testFallbackPreservesEmptyExceptionMessage(): void
    {
        $client = new class implements OpenAiClientInterface {
            public function chat(array $messages, ?string $model = null): array
            {
                throw new RuntimeException('');
            }
        };

        $useCase = new GenerateContent($client);
        $result = $useCase->handle([]);

        $decoded = json_decode($result['content'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('⚠️ No se pudo generar el cómic', $decoded['summary'] ?? null);
    }

    public function testStripsJsonCodeFenceVariants(): void
    {
        // Test with ```json prefix (already tested in base test)
        $client = $this->fakeClient([
            'choices' => [
                ['message' => ['content' => "```\n{\"title\":\"plain fence\"}\n```"]],
            ],
        ]);

        $useCase = new GenerateContent($client);
        $result = $useCase->handle([]);

        $this->assertSame('{"title":"plain fence"}', $result['content']);
    }

    public function testRawResponsePreserved(): void
    {
        $apiResponse = [
            'choices' => [['message' => ['content' => 'test']]],
            'id' => 'chatcmpl-abc123',
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
        ];
        $client = $this->fakeClient($apiResponse);

        $useCase = new GenerateContent($client);
        $result = $useCase->handle([]);

        $this->assertSame($apiResponse, $result['raw']);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function fakeClient(array $response): OpenAiClientInterface
    {
        return new class ($response) implements OpenAiClientInterface {
            /** @param array<string, mixed> $response */
            public function __construct(private readonly array $response)
            {
            }

            public function chat(array $messages, ?string $model = null): array
            {
                return $this->response;
            }
        };
    }
}
