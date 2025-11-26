<?php

declare(strict_types=1);

header('Content-Type: application/json');

$question = $_POST['question'] ?? null;

if (!$question) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing question']);
    exit;
}

$ragUrl = $_ENV['RAG_SERVICE_URL'] ?? getenv('RAG_SERVICE_URL');
if (!is_string($ragUrl) || trim($ragUrl) === '') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = is_string($host) ? strtolower($host) : '';
    $ragUrl = str_contains($host, 'creawebes.com')
        ? 'https://rag-service.contenido.creawebes.com/rag/agent'
        : 'http://localhost:8082/rag/agent';
}

$payload = json_encode(['question' => $question]);

$ch = curl_init($ragUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload
]);

$response = curl_exec($ch);
$err = curl_error($ch);

curl_close($ch);

if ($err) {
    echo json_encode(['ok' => false, 'error' => $err]);
    exit;
}

echo $response;
