<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Infrastructure\Client;

use Creawebes\OpenAI\Application\Contracts\OpenAiClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class OpenAiClient implements OpenAiClientInterface
{
    private Client $httpClient;
    private string $apiKey;
    private string $defaultModel;

    public function __construct(
        ?Client $httpClient = null,
        ?string $apiKey = null,
        ?string $baseUri = null,
        ?string $defaultModel = null
    ) {
        $this->apiKey = $this->resolveApiKey($apiKey);
        $resolvedBaseUri = $this->resolveBaseUri($baseUri);
        $this->defaultModel = $this->resolveModel($defaultModel);

        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $resolvedBaseUri,
            'timeout' => 30,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    public function chat(array $messages, ?string $model = null): array
    {
        try {
            $response = $this->httpClient->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model ?? $this->defaultModel,
                    'messages' => $messages,
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new OpenAiClientException('Error al comunicarse con OpenAI', 0, $exception);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new OpenAiClientException('OpenAI devolvió un error HTTP: ' . $statusCode);
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new OpenAiClientException('Respuesta inválida desde OpenAI');
        }

        return $decoded;
    }

    private function resolveApiKey(?string $apiKey): string
    {
        $key = $apiKey ?? $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: null;
        if (!is_string($key) || trim($key) === '') {
            throw new OpenAiClientException('OPENAI_API_KEY no configurada');
        }

        return trim($key);
    }

    private function resolveBaseUri(?string $baseUri): string
    {
        $candidate = $baseUri ?? $_ENV['OPENAI_API_BASE'] ?? getenv('OPENAI_API_BASE') ?: 'https://api.openai.com/v1';
        $trimmed = rtrim((string) $candidate, '/');

        return $trimmed . '/';
    }

    private function resolveModel(?string $defaultModel): string
    {
        $candidate = $defaultModel ?? $_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';

        return trim((string) $candidate) !== '' ? trim((string) $candidate) : 'gpt-4o-mini';
    }
}
