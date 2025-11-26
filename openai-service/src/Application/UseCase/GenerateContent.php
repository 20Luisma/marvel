<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Application\UseCase;

use Creawebes\OpenAI\Application\Contracts\OpenAiClientInterface;
use Throwable;

final class GenerateContent
{
    public function __construct(private readonly OpenAiClientInterface $client)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    public function handle(array $messages): string
    {
        try {
            $response = $this->client->chat($messages);
            $content = $this->extractContent($response);

            if ($content === null) {
                return $this->buildFallbackStory('⚠️ OpenAI devolvió un formato inesperado.');
            }

            return $this->stripCodeFence($content);
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $summary = $message !== '' ? $message : '⚠️ No se pudo generar el cómic';

            return $this->buildFallbackStory($summary);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractContent(array $data): ?string
    {
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            return null;
        }

        $trimmed = trim($content);

        return $trimmed === '' ? null : $trimmed;
    }

    private function stripCodeFence(string $text): string
    {
        $trimmed = trim($text);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        return $trimmed;
    }

    private function buildFallbackStory(string $message): string
    {
        $payload = [
            'title' => 'No se pudo generar el cómic',
            'summary' => $message,
            'panels' => [],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json !== false ? $json : '{"title":"No se pudo generar el cómic","summary":"Error desconocido","panels":[]}';
    }
}
