<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__, 2);
$storageDir = $rootPath . '/storage/marvel';
$dataFile = $storageDir . '/ultimo-video-marvel.json';
$legacyFile = dirname(__DIR__) . '/api/ultimo-video-marvel.json';

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
    if ($allowedOrigin === $originHeader) {
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Vary: Origin');
    } else {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Origen no permitido']);
        exit;
    }
}

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$sourceFile = null;
if (is_file($dataFile)) {
    $sourceFile = $dataFile;
} elseif (is_file($legacyFile)) {
    // Fallback: usar el archivo legado y copiarlo a storage para siguientes lecturas.
    $sourceFile = $legacyFile;
    @mkdir($storageDir, 0775, true);
    @copy($legacyFile, $dataFile);
}

if ($sourceFile === null) {
    echo json_encode(['status' => 'ok', 'data' => null]);
    exit;
}

$json = file_get_contents($sourceFile);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo leer el contenido.']);
    exit;
}

// Entregamos el contenido tal cual lo escribió n8n.
echo $json;
