<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Service;

final class OpenAIChatService
{
    /**
     * @param array<int, array<string, string>> $messages
     */
    public function generateStory(array $messages): string
    {
        $apiKey = getenv('OPENAI_API_KEY');
        if (!is_string($apiKey) || trim($apiKey) === '') {
            return $this->buildFallbackStory('⚠️ No se ha configurado OPENAI_API_KEY en el entorno.');
        }

        $model = getenv('OPENAI_MODEL');
        if (!is_string($model) || trim($model) === '') {
            $model = 'gpt-4o-mini';
        }

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.8,
        ];

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return $this->buildFallbackStory('⚠️ No se pudo preparar la petición para OpenAI.');
        }

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        if ($ch === false) {
            return $this->buildFallbackStory('⚠️ No se pudo inicializar la petición a OpenAI.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return $this->buildFallbackStory('⚠️ Error al llamar a OpenAI: ' . ($curlError !== '' ? $curlError : 'respuesta vacía'));
        }

        if ($httpCode >= 400) {
            return $this->buildFallbackStory('⚠️ Error al llamar a OpenAI. Código: ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return $this->buildFallbackStory('⚠️ OpenAI devolvió un formato inválido.');
        }

        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            return $this->buildFallbackStory('⚠️ OpenAI devolvió un formato inesperado.');
        }

        return $this->stripCodeFence($content);
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

    private function stripCodeFence(string $text): string
    {
        $trimmed = trim($text);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        return $trimmed;
    }
}
