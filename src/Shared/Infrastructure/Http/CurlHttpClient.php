<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use RuntimeException;

final class CurlHttpClient implements HttpClientInterface
{
    private const BASE_BACKOFF_MS = 250;
    private const MAX_BACKOFF_MS = 3000;

    public function __construct(private readonly ?string $internalToken = null)
    {
    }

    /**
     * @param array<string, string> $headers
     */
    public function postJson(string $url, array|string $payload, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse
    {
        $attempts = max(1, $retries + 1);
        $encodedPayload = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encodedPayload === false) {
            throw new RuntimeException('No se pudo codificar el payload JSON.');
        }

        $baseHeaders = ['Content-Type: application/json'];
        if ($this->internalToken !== null && $this->internalToken !== '') {
            $baseHeaders[] = 'X-Internal-Token: ' . $this->internalToken;
        }

        $headerLines = array_merge($baseHeaders, $this->formatHeaders($headers));

        $lastError = null;
        for ($i = 0; $i < $attempts; $i++) {
            $ch = curl_init($url);
            if ($ch === false) {
                $lastError = 'No se pudo inicializar cURL.';
                continue;
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedPayload);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutSeconds);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);

            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false || $httpCode === 0) {
                $lastError = $error !== '' ? $error : 'Sin respuesta del servidor';
                if ($i + 1 < $attempts) {
                    $this->sleepBackoff($i);
                    continue;
                }
                throw new RuntimeException('Fallo al llamar al servicio: ' . $lastError);
            }

            return new HttpResponse($httpCode, (string) $body);
        }

        throw new RuntimeException('Fallo al llamar al servicio' . ($lastError ? ': ' . $lastError : ''));
    }

    /**
     * @param array<string, string> $headers
     * @return array<int, string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = sprintf('%s: %s', $name, $value);
        }

        return $formatted;
    }

    private function sleepBackoff(int $attempt): void
    {
        $milliseconds = min(
            self::MAX_BACKOFF_MS,
            (int) (self::BASE_BACKOFF_MS * (2 ** $attempt))
        );

        usleep($milliseconds * 1000);
    }
}
