<?php
// public/api/actualizar-video-marvel.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// aceptar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'msg' => 'preflight']);
    exit;
}

$raw = file_get_contents('php://input');

// log para comprobar que llega
$logFile = __DIR__ . '/log-marvel.txt';
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
    __DIR__ . '/ultimo-video-marvel.json',
    json_encode($video, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo json_encode(['status' => 'ok', 'saved' => $video]);
