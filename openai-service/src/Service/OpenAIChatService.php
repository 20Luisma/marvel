<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Service;

use CurlHandle;

final class OpenAIChatService
{
    /**
     * @param array<int, array<string, string>> $messages
     */
    public function generateStory(array $messages): string
    {
        $result = $this->requestChatCompletion($messages);

        return $result['success']
            ? $this->stripCodeFence($result['content'])
            : $this->buildFallbackStory($result['error']);
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

    /**
     * @param array<int, array<string, string>> $messages
     * @return array{success:bool,content?:string,error?:string}
     */
    private function requestChatCompletion(array $messages): array
    {
        $apiKey = $this->resolveApiKey();
        $error = null;
        $content = null;

        if ($apiKey === null) {
            $error = '⚠️ No se ha configurado OPENAI_API_KEY en el entorno.';
        } else {
            $payload = $this->encodePayload($this->resolveModel(), $messages);
            if ($payload === null) {
                $error = '⚠️ No se pudo preparar la petición para OpenAI.';
            } else {
                $handle = $this->initializeCurlHandle($apiKey, $payload);
                if ($handle === null) {
                    $error = '⚠️ No se pudo inicializar la petición a OpenAI.';
                } else {
                    $execution = $this->executeCurl($handle);
                    if ($execution['response'] === null) {
                        $errorMessage = $execution['error'] !== '' ? $execution['error'] : 'respuesta vacía';
                        $error = '⚠️ Error al llamar a OpenAI: ' . $errorMessage;
                    } elseif ($execution['status'] >= 400) {
                        $error = '⚠️ Error al llamar a OpenAI. Código: ' . $execution['status'];
                    } else {
                        $data = $this->decodeResponse($execution['response']);
                        if ($data === null) {
                            $error = '⚠️ OpenAI devolvió un formato inválido.';
                        } else {
                            $content = $this->extractContent($data);
                            if ($content === null) {
                                $error = '⚠️ OpenAI devolvió un formato inesperado.';
                            }
                        }
                    }
                }
            }
        }

        if ($error !== null) {
            return $this->failureResult($error);
        }

        return [
            'success' => true,
            'content' => $content,
        ];
    }

    private function resolveApiKey(): ?string
    {
        $apiKey = getenv('OPENAI_API_KEY');
        if (!is_string($apiKey)) {
            return null;
        }

        $trimmed = trim($apiKey);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resolveModel(): string
    {
        $model = getenv('OPENAI_MODEL');
        if (!is_string($model) || trim($model) === '') {
            return 'gpt-4o-mini';
        }

        return trim($model);
    }

    /**
     * @param array<int, array<string, string>> $messages
     */
    private function encodePayload(string $model, array $messages): ?string
    {
        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.8,
        ];

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $payload === false ? null : $payload;
    }

    /**
     * @return CurlHandle|false
     */
    private function initializeCurlHandle(string $apiKey, string $payload)
    {
        $handle = curl_init('https://api.openai.com/v1/chat/completions');
        if ($handle === false) {
            return false;
        }

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);

        return $handle;
    }

    /**
     * @param CurlHandle $handle
     * @return array{response:?string,status:int,error:string}
     */
    private function executeCurl($handle): array
    {
        $response = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        return [
            'response' => $response === false ? null : $response,
            'status' => $httpCode,
            'error' => $curlError,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeResponse(string $response): ?array
    {
        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
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

    /**
     * @return array{success:false,error:string}
     */
    private function failureResult(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
        ];
    }
}
