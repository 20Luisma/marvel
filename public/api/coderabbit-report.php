<?php
// public/api/coderabbit-report.php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$rootPath = dirname(__DIR__, 2);
$envFile = $rootPath . '/.env';

/**
 * Carga el .env si aún no existe la variable en el entorno.
 */
function ensureEnv(string $envFile): void
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

ensureEnv($envFile);

/**
 * @return string|null
 */
function envv(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

$apiKey = envv('CODERABBIT_API_KEY');
if ($apiKey === null || $apiKey === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Falta CODERABBIT_API_KEY en .env']);
    exit;
}

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-14 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

$payload = [
    'from' => $from,
    'to' => $to,
];

/**
 * @param array<string, mixed>|null $payload
 * @return array{ok: bool, status: int, body: string, decoded: mixed, error?: string}
 */
function requestCoderabbit(string $url, string $method, ?array $payload, string $apiKey): array
{
    $ch = curl_init($url);
    $headers = [
        'accept: application/json',
        'x-coderabbitai-api-key: ' . $apiKey,
        'user-agent: clean-marvel-album',
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ];

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

$reportResponse = requestCoderabbit(
    'https://api.coderabbit.ai/api/v1/report.generate',
    'POST',
    $payload,
    $apiKey
);

if ($reportResponse['ok']) {
    // Pasar la respuesta tal cual llega desde CodeRabbit.
    echo $reportResponse['body'];
    exit;
}

/**
 * Intenta recuperar información básica para verificar la conectividad aunque Reports no esté disponible.
 *
 * @return array<int, array{group: string, report: string}>|null
 */
function fetchProjectsFallback(string $apiKey, array $reportResponse): ?array
{
    $projectsResponse = requestCoderabbit(
        'https://api.coderabbit.ai/api/v1/projects',
        'GET',
        null,
        $apiKey
    );

    if (!$projectsResponse['ok'] || !is_array($projectsResponse['decoded'])) {
        return null;
    }

    $projects = $projectsResponse['decoded'];
    $lines = [
        "**Reports no disponible**:",
        "- Código HTTP: " . ($reportResponse['status'] ?: '0'),
    ];

    if (is_array($reportResponse['decoded'])) {
        $remoteMessage = $reportResponse['decoded']['message'] ?? $reportResponse['decoded']['error'] ?? null;
        if ($remoteMessage) {
            $lines[] = "- Mensaje: " . $remoteMessage;
        }
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

$fallback = fetchProjectsFallback($apiKey, $reportResponse);

if ($fallback !== null) {
    echo json_encode($fallback, JSON_UNESCAPED_UNICODE);
    exit;
}

$status = $reportResponse['status'] ?: 502;
http_response_code($status);

$errorPayload = [
    'error' => 'CodeRabbit API rechazó la solicitud.',
    'status' => $status,
];

if ($reportResponse['decoded'] !== null) {
    $errorPayload['body'] = $reportResponse['decoded'];
} elseif ($reportResponse['body'] !== '') {
    $errorPayload['body'] = $reportResponse['body'];
}

if (!empty($reportResponse['error'])) {
    $errorPayload['transport_error'] = $reportResponse['error'];
}

echo json_encode($errorPayload, JSON_UNESCAPED_UNICODE);
