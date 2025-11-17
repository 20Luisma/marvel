<?php

declare(strict_types=1);

if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false) {
    header('Location: /comic');
    exit;
}

$rootPath = dirname(__DIR__, 2);
$envPath = $rootPath . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

$allowedOrigin = trim((string) ($_ENV['APP_ORIGIN'] ?? $_ENV['APP_URL'] ?? ''));
$requestOrigin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');

if ($allowedOrigin !== '' && $requestOrigin !== '') {
    if ($requestOrigin === $allowedOrigin) {
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Vary: Origin');
    } else {
        jsonResponse(403, 'Origen no permitido para este recurso.');
    }
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$token = trim((string) ($_ENV['TTS_INTERNAL_TOKEN'] ?? ''));
// Si no hay token configurado en entorno, mantenemos compatibilidad y permitimos la petición.
if ($token !== '' && !hash_equals($token, parseBearer($authHeader))) {
    jsonResponse(403, 'No autorizado para solicitar TTS.');
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(405, 'Método no permitido. Usa POST para solicitar audio.');
}

$input = file_get_contents('php://input') ?: '';
$payload = json_decode($input, true);
if (!is_array($payload)) {
    jsonResponse(400, 'El payload debe ser JSON válido.');
}

$text = isset($payload['text']) ? trim((string) $payload['text']) : '';
if ($text === '') {
    jsonResponse(422, 'Debes enviar el texto que deseas convertir a audio.');
}

$maxLength = 4800; // alineado con MAX_TTS_CHARACTERS del frontend
if (function_exists('mb_strlen')) {
    $textLength = mb_strlen($text);
    if ($textLength > $maxLength) {
        jsonResponse(422, 'El texto no puede superar ' . $maxLength . ' caracteres.');
    }
} elseif (strlen($text) > $maxLength) {
    jsonResponse(422, 'El texto no puede superar ' . $maxLength . ' caracteres.');
}

$apiKey = getenv('ELEVENLABS_API_KEY') ?: ($_ENV['ELEVENLABS_API_KEY'] ?? '');
if (!$apiKey) {
    jsonResponse(500, 'Configura ELEVENLABS_API_KEY en tu entorno para habilitar el audio.');
}

$voiceId = (string) ($payload['voiceId']
    ?? $_ENV['ELEVENLABS_VOICE_ID']
    ?? getenv('ELEVENLABS_VOICE_ID')
    ?? 'EXAVITQu4vr4xnSDxMaL');
$voiceId = trim($voiceId) !== '' ? trim($voiceId) : 'EXAVITQu4vr4xnSDxMaL';

$modelId = (string) ($payload['modelId']
    ?? $_ENV['ELEVENLABS_MODEL_ID']
    ?? getenv('ELEVENLABS_MODEL_ID')
    ?? 'eleven_multilingual_v2');
$modelId = trim($modelId) !== '' ? trim($modelId) : 'eleven_multilingual_v2';

$outputFormat = (string) ($payload['outputFormat'] ?? 'mp3_44100_128');

$stability = (float) ($payload['stability']
    ?? $_ENV['ELEVENLABS_VOICE_STABILITY']
    ?? getenv('ELEVENLABS_VOICE_STABILITY')
    ?? 0.55);
$similarity = (float) ($payload['similarityBoost']
    ?? $_ENV['ELEVENLABS_VOICE_SIMILARITY']
    ?? getenv('ELEVENLABS_VOICE_SIMILARITY')
    ?? 0.75);
$stability = max(0, min(1, $stability));
$similarity = max(0, min(1, $similarity));

$requestBody = json_encode([
    'text' => $text,
    'model_id' => $modelId,
    'voice_settings' => [
        'stability' => $stability,
        'similarity_boost' => $similarity,
    ],
    'output_format' => $outputFormat,
], JSON_UNESCAPED_UNICODE);

if ($requestBody === false) {
    jsonResponse(500, 'No se pudo preparar la solicitud para ElevenLabs.');
}

$endpoint = sprintf('https://api.elevenlabs.io/v1/text-to-speech/%s', rawurlencode($voiceId));
$handle = curl_init($endpoint);

if ($handle === false) {
    jsonResponse(500, 'No se pudo iniciar la solicitud hacia ElevenLabs.');
}

curl_setopt_array($handle, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: audio/mpeg',
        'xi-api-key: ' . $apiKey,
    ],
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response = curl_exec($handle);
$statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
$curlError = curl_error($handle);
curl_close($handle);

if ($response === false) {
    jsonResponse(502, 'No se pudo contactar ElevenLabs: ' . ($curlError ?: 'Error desconocido.'));
}

if ($statusCode < 200 || $statusCode >= 300) {
    $errorPayload = json_decode($response, true);
    $message = $errorPayload['detail']['message']
        ?? $errorPayload['message']
        ?? 'ElevenLabs devolvió un error inesperado.';
    jsonResponse($statusCode ?: 502, $message);
}

header('Content-Type: audio/mpeg');
header('Content-Disposition: inline; filename="clean-marvel-story.mp3"');
header('Cache-Control: no-store, max-age=0');
http_response_code(200);
echo $response;
exit;

function jsonResponse(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode([
        'status' => 'error',
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Extrae el valor Bearer de la cabecera Authorization.
 */
function parseBearer(string $authorizationHeader): string
{
    if (stripos($authorizationHeader, 'Bearer ') === 0) {
        return trim(substr($authorizationHeader, 7));
    }
    return '';
}
