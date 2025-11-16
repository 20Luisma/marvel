<?php
// public/api/actualizar-video-marvel.php

$rootPath = dirname(__DIR__, 2);
$envPath = $rootPath . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        if ($k !== '') {
            $_ENV[$k] = $v;
            putenv($k . '=' . $v);
        }
    }
}

$allowedOrigin = trim((string) ($_ENV['APP_ORIGIN'] ?? $_ENV['APP_URL'] ?? ''));
$originHeader = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($allowedOrigin !== '' && $originHeader !== '') {
    if ($originHeader === $allowedOrigin) {
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Vary: Origin');
    } else {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Origen no permitido']);
        exit;
    }
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// aceptar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'msg' => 'preflight']);
    exit;
}

$authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$expectedToken = trim((string) ($_ENV['MARVEL_UPDATE_TOKEN'] ?? ''));
if ($expectedToken !== '' && !hash_equals($expectedToken, bearerToken($authHeader))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
if (strlen($raw) > 10 * 1024) {
    http_response_code(413);
    echo json_encode(['status' => 'error', 'message' => 'Payload demasiado grande (máx 10KB)']);
    exit;
}

$storageDir = $rootPath . '/storage/marvel';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

// log para comprobar que llega (fuera de public/)
$logFile = $storageDir . '/log-marvel.txt';
if (is_file($logFile) && filesize($logFile) > 1024 * 1024) {
    rename($logFile, $logFile . '.' . date('Ymd_His') . '.bak');
}
file_put_contents($logFile, date('Y-m-d H:i:s') . " => " . $raw . PHP_EOL, FILE_APPEND);

$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['status' => 'ok', 'warning' => 'JSON inválido o vacío']);
    exit;
}

$item = $data['items'][0] ?? null;
if (!$item) {
    echo json_encode(['status' => 'ok', 'warning' => 'no items en la respuesta', 'raw' => $data]);
    exit;
}

$video = [
    'videoId'      => $item['id']['videoId'] ?? '',
    'title'        => $item['snippet']['title'] ?? '',
    'description'  => $item['snippet']['description'] ?? '',
    'publishedAt'  => $item['snippet']['publishedAt'] ?? '',
    'thumbnail'    => $item['snippet']['thumbnails']['high']['url'] ?? '',
    'channelTitle' => $item['snippet']['channelTitle'] ?? '',
];

// guardar JSON para que lo lea el frontend
file_put_contents(
    $storageDir . '/ultimo-video-marvel.json',
    json_encode($video, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo json_encode(['status' => 'ok', 'saved' => $video]);

function bearerToken(string $header): string
{
    return stripos($header, 'Bearer ') === 0 ? trim(substr($header, 7)) : '';
}
