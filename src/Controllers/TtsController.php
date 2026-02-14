<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Shared\Http\JsonResponse;

final class TtsController
{
    private const MAX_LENGTH = 4800;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey,
        private readonly string $defaultVoiceId,
        private readonly string $defaultModelId
    ) {
    }

    public function generate(): void
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            JsonResponse::error('Configura ELEVENLABS_API_KEY en tu entorno para habilitar el audio.', 500);
            return;
        }

        $rawBody = $_SERVER['MARVEL_RAW_BODY'] ?? file_get_contents('php://input');
        $payload = json_decode((string)$rawBody, true);

        if (!is_array($payload)) {
            JsonResponse::error('El payload debe ser JSON válido.', 400);
            return;
        }

        $text = isset($payload['text']) ? trim((string) $payload['text']) : '';
        if ($text === '') {
            JsonResponse::error('Debes enviar el texto que deseas convertir a audio.', 422);
            return;
        }

        if (mb_strlen($text) > self::MAX_LENGTH) {
            JsonResponse::error('El texto no puede superar ' . self::MAX_LENGTH . ' caracteres.', 422);
            return;
        }

        $voiceId = trim((string) ($payload['voiceId'] ?? $this->defaultVoiceId));
        if ($voiceId === '') {
            $voiceId = $this->defaultVoiceId;
        }

        $modelId = trim((string) ($payload['modelId'] ?? $this->defaultModelId));
        if ($modelId === '') {
            $modelId = $this->defaultModelId;
        }

        $outputFormat = (string) ($payload['outputFormat'] ?? 'mp3_44100_128');
        $stability = (float) ($payload['stability'] ?? 0.55);
        $similarity = (float) ($payload['similarityBoost'] ?? 0.75);

        $requestPayload = [
            'text' => $text,
            'model_id' => $modelId,
            'voice_settings' => [
                'stability' => max(0, min(1, $stability)),
                'similarity_boost' => max(0, min(1, $similarity)),
            ],
            'output_format' => $outputFormat,
        ];

        $endpoint = sprintf('https://api.elevenlabs.io/v1/text-to-speech/%s', rawurlencode($voiceId));

        try {
            $response = $this->httpClient->postJson(
                $endpoint,
                (string)json_encode($requestPayload),
                [
                    'xi-api-key' => $this->apiKey,
                    'Accept' => 'audio/mpeg',
                ],
                timeoutSeconds: 60
            );

            if ($response->statusCode !== 200) {
                $errorData = json_decode($response->body, true);
                $message = $errorData['detail']['message'] ?? $errorData['message'] ?? 'ElevenLabs devolvió un error inesperado.';
                JsonResponse::error($message, $response->statusCode);
                return;
            }

            header('Content-Type: audio/mpeg');
            header('Content-Disposition: inline; filename="clean-marvel-story.mp3"');
            header('Cache-Control: no-store, max-age=0');
            http_response_code(200);
            echo $response->body;

        } catch (\Throwable $e) {
            JsonResponse::error('Error contactando ElevenLabs: ' . $e->getMessage(), 502);
        }
    }
}
