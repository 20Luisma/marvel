<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Tests\Infrastructure;

use Creawebes\OpenAI\Infrastructure\Client\OpenAiClient;
use Creawebes\OpenAI\Infrastructure\Client\OpenAiClientException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class OpenAiClientTest extends TestCase
{
    public function testChatReturnsDecodedResponse(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(['choices' => [['message' => ['content' => 'hola']]]], JSON_UNESCAPED_UNICODE)
            ),
        ]);
        $client = $this->createClient($mock);

        $result = $client->chat([['role' => 'user', 'content' => 'hola']]);

        $this->assertIsArray($result);
        $this->assertSame('hola', $result['choices'][0]['message']['content'] ?? null);
    }

    public function testChatThrowsOnHttpError(): void
    {
        $mock = new MockHandler([
            new Response(500, [], 'error'),
        ]);
        $client = $this->createClient($mock);

        $this->expectException(OpenAiClientException::class);
        $client->chat([]);
    }

    public function testChatThrowsOnInvalidJson(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'not-json'),
        ]);
        $client = $this->createClient($mock);

        $this->expectException(OpenAiClientException::class);
        $client->chat([]);
    }

    public function testConstructorFailsWithoutApiKey(): void
    {
        $this->clearEnv('OPENAI_API_KEY');

        $this->expectException(OpenAiClientException::class);
        new OpenAiClient($this->createHttpClient(new MockHandler([])), apiKey: null);
    }

    private function createClient(MockHandler $mock): OpenAiClient
    {
        $this->setEnv('OPENAI_API_KEY', 'test-key');

        return new OpenAiClient(
            $this->createHttpClient($mock),
            apiKey: null,
            baseUri: 'https://api.openai.com/v1',
            defaultModel: 'gpt-4o-mini'
        );
    }

    private function createHttpClient(MockHandler $mock): Client
    {
        return new Client(['handler' => HandlerStack::create($mock)]);
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function clearEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}
