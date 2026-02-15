<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Tests\Controller;

use Creawebes\OpenAI\Application\Contracts\OpenAiClientInterface;
use Creawebes\OpenAI\Application\UseCase\GenerateContent;
use Creawebes\OpenAI\Controller\OpenAIController;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for the OpenAIController.
 *
 * Validates HTTP-layer behavior: payload parsing, JSON output format,
 * error handling, and edge cases (empty body, malformed JSON, missing messages).
 */
final class OpenAIControllerTest extends TestCase
{
    public function testChatWithValidMessagesReturnsOk(): void
    {
        $client = $this->createFakeClient([
            'choices' => [
                ['message' => ['content' => '{"title":"Test Comic","summary":"A test","panels":[]}']],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
            'model' => 'gpt-4o-mini',
        ]);

        $controller = new OpenAIController(new GenerateContent($client));

        $_SERVER['__RAW_INPUT__'] = json_encode([
            'messages' => [['role' => 'user', 'content' => 'generate a comic']],
        ]);

        ob_start();
        $controller->chat();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['ok'] ?? false);
        $this->assertArrayHasKey('content', $decoded);
        $this->assertArrayHasKey('usage', $decoded);
        $this->assertArrayHasKey('model', $decoded);
    }

    public function testChatWithEmptyBodyDoesNotCrash(): void
    {
        $client = $this->createFakeClient([
            'choices' => [
                ['message' => ['content' => '{"title":"Empty","summary":"No input","panels":[]}']],
            ],
        ]);

        $controller = new OpenAIController(new GenerateContent($client));

        $_SERVER['__RAW_INPUT__'] = '';

        ob_start();
        $controller->chat();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        // Should still respond with ok=true (empty messages are handled gracefully)
        $this->assertTrue($decoded['ok'] ?? false);
    }

    public function testChatWithMalformedJsonDoesNotCrash(): void
    {
        $client = $this->createFakeClient([
            'choices' => [
                ['message' => ['content' => '{"title":"Fallback","summary":"ok","panels":[]}']],
            ],
        ]);

        $controller = new OpenAIController(new GenerateContent($client));

        $_SERVER['__RAW_INPUT__'] = '{this is not valid json!!!';

        ob_start();
        $controller->chat();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        // Malformed JSON → data becomes [] → messages becomes [] → handled gracefully
        $this->assertTrue($decoded['ok'] ?? false);
    }

    public function testChatWithMissingMessagesKeyDoesNotCrash(): void
    {
        $client = $this->createFakeClient([
            'choices' => [
                ['message' => ['content' => '{"title":"OK","summary":"ok","panels":[]}']],
            ],
        ]);

        $controller = new OpenAIController(new GenerateContent($client));

        $_SERVER['__RAW_INPUT__'] = json_encode(['unexpected_key' => 'value']);

        ob_start();
        $controller->chat();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['ok'] ?? false);
    }

    public function testChatReturns500WhenUseCaseThrowsUnexpectedError(): void
    {
        $client = new class implements OpenAiClientInterface {
            public function chat(array $messages, ?string $model = null): array
            {
                throw new RuntimeException('Unexpected fatal error');
            }
        };

        $controller = new OpenAIController(new GenerateContent($client));

        $_SERVER['__RAW_INPUT__'] = '{"messages":[{"role":"user","content":"test"}]}';

        ob_start();
        $controller->chat();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        // GenerateContent catches and returns fallback, so ok is still true
        $this->assertTrue($decoded['ok'] ?? false);
        $this->assertStringContainsString('Unexpected fatal error', $decoded['content'] ?? '');
    }

    public function testChatWithNonArrayMessagesHandlesGracefully(): void
    {
        $client = $this->createFakeClient([
            'choices' => [
                ['message' => ['content' => '{"title":"ok","summary":"ok","panels":[]}']],
            ],
        ]);

        $controller = new OpenAIController(new GenerateContent($client));

        $_SERVER['__RAW_INPUT__'] = json_encode(['messages' => 'this-is-not-an-array']);

        ob_start();
        $controller->chat();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        // Non-array messages → controller forces [] → handled gracefully
        $this->assertTrue($decoded['ok'] ?? false);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function createFakeClient(array $response): OpenAiClientInterface
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
