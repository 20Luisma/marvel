<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class OpenAIClient
{
    private Client $client;
    private string $apiKey;
    private string $defaultModel;

    public function __construct(?Client $client = null)
    {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
        if (!$apiKey) {
            throw new OpenAIClientException('OPENAI_API_KEY no configurada');
        }
        $this->apiKey = $apiKey;

        $baseUri = $_ENV['OPENAI_API_BASE'] ?? getenv('OPENAI_API_BASE') ?: 'https://api.openai.com/v1';
        $this->defaultModel = $_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';

        $this->client = $client ?? new Client([
            'base_uri' => rtrim($baseUri, '/') . '/',
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
            $response = $this->client->post('chat/completions', [
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
            throw new OpenAIClientException('Error al comunicarse con OpenAI', 0, $exception);
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new OpenAIClientException('Respuesta inválida desde OpenAI');
        }

        return $decoded;
    }
}
