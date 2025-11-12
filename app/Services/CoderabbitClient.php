<?php

namespace App\Services;

@ini_set('max_execution_time', '650');
@ini_set('default_socket_timeout', '650');
@set_time_limit(650);

final class CoderabbitClient
{
    private string $apiKey;
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, '/\\');
        $envFile = $this->rootPath . '/.env';
        self::ensureEnv($envFile);
        $this->apiKey = (string) self::envv('CODERABBIT_API_KEY', '');
    }

    public static function ensureEnv(string $envFile): void
    {
        static $loaded = false;
        if ($loaded || !is_file($envFile)) {
            return;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if ($line === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }

        $loaded = true;
    }

    public static function envv(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{ok: bool, status: int, body: string, decoded: mixed, error?: string}
     */
    private static function requestCoderabbit(string $url, string $method, ?array $payload, string $apiKey): array
    {
        $ch = curl_init($url);
        $headers = [
            'accept: application/json',
            'x-coderabbitai-api-key: ' . $apiKey,
            'user-agent: clean-marvel-album',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if (defined('CURL_HTTP_VERSION_2TLS')) {
            $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2TLS;
        }

        if ($method === 'POST') {
            $headers[] = 'content-type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        } else {
            $options[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'decoded' => null, 'error' => $error];
        }

        $decoded = json_decode($body, true);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $body,
            'decoded' => $decoded,
            'error' => $error ?: null,
        ];
    }

    /**
     * @return array<int, array{group: string, report: string}>|array<string, mixed>
     */
    public function generateReport(string $from, string $to): array
    {
        if ($this->apiKey === '') {
            return [
                'error' => 'Falta CODERABBIT_API_KEY en .env',
                'status' => 400,
            ];
        }

        $payload = [
            'from' => $from,
            'to' => $to,
        ];

        $reportResponse = self::requestCoderabbit(
            'https://api.coderabbit.ai/api/v1/report.generate',
            'POST',
            $payload,
            $this->apiKey
        );

        if ($reportResponse['ok'] && is_array($reportResponse['decoded'])) {
            return $reportResponse['decoded'];
        }

        $fallback = $this->fetchProjectsFallback($reportResponse);
        if ($fallback !== null) {
            return $fallback;
        }

        $status = $reportResponse['status'] ?: 502;
        $errorPayload = [
            'error' => 'CodeRabbit API rechazó la solicitud.',
            'status' => $status,
        ];

        $remoteMessage = self::extractRemoteMessage($reportResponse['decoded']);
        if ($remoteMessage !== null) {
            $errorPayload['remote_message'] = $remoteMessage;
        }

        if ($reportResponse['decoded'] !== null) {
            $errorPayload['body'] = $reportResponse['decoded'];
        } elseif ($reportResponse['body'] !== '') {
            $errorPayload['body'] = $reportResponse['body'];
        }

        if (!empty($reportResponse['error'])) {
            $errorPayload['transport_error'] = $reportResponse['error'];
            $errorPayload['detail'] = $reportResponse['error'];
        }

        return $errorPayload;
    }

    /**
     * @return array<int, array{group: string, report: string}>|null
     */
    private function fetchProjectsFallback(array $reportResponse): ?array
    {
        $projectsResponse = self::requestCoderabbit(
            'https://api.coderabbit.ai/api/v1/projects',
            'GET',
            null,
            $this->apiKey
        );

        if (!$projectsResponse['ok'] || !is_array($projectsResponse['decoded'])) {
            return null;
        }

        $projects = $projectsResponse['decoded'];
        $lines = [
            "**Reports no disponible**:",
            "- Código HTTP: " . ($reportResponse['status'] ?: '0'),
        ];

        $remoteMessage = self::extractRemoteMessage($reportResponse['decoded']);
        if ($remoteMessage) {
            $lines[] = "- Mensaje: " . $remoteMessage;
        }

        $lines[] = "";
        $lines[] = "**Proyectos accesibles (fallback):**";

        $count = 0;
        foreach ($projects as $project) {
            if (!is_array($project)) {
                continue;
            }

            $name = $project['name'] ?? ($project['id'] ?? 'Proyecto sin nombre');
            $org = $project['organization'] ?? ($project['org'] ?? 'org desconocida');
            $lines[] = "- {$name} ({$org})";
            $count++;

            if ($count >= 5) {
                break;
            }
        }

        if ($count === 0) {
            $lines[] = '- No se recibieron proyectos en la API.';
        }

        return [
            [
                'group' => 'CodeRabbit – Diagnóstico',
                'report' => implode("\n", $lines),
            ],
        ];
    }

    /**
     * @param mixed $decoded
     */
    private static function extractRemoteMessage($decoded): ?string
    {
        if (!is_array($decoded)) {
            return null;
        }

        foreach (['message', 'error'] as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key]) && $decoded[$key] !== '') {
                return $decoded[$key];
            }
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            foreach (['message', 'error'] as $key) {
                if (isset($decoded['error'][$key]) && is_string($decoded['error'][$key]) && $decoded['error'][$key] !== '') {
                    return $decoded['error'][$key];
                }
            }
        }

        return null;
    }
}
