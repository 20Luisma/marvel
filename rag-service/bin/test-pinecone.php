<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar .env manualmente
$rootPath = dirname(__DIR__);
$envPath = $rootPath . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with($line, '#')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

$apiKey = $_ENV['PINECONE_API_KEY'] ?? getenv('PINECONE_API_KEY');
$host = $_ENV['PINECONE_INDEX_HOST'] ?? getenv('PINECONE_INDEX_HOST');

echo "Testing with API Key: " . substr($apiKey, 0, 10) . "...\n";
echo "Testing with Host: $host\n";

$url = rtrim($host, '/') . '/describe_index_stats';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Api-Key: ' . $apiKey,
]);

curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Headers:\n$header\n";
echo "Body: $body\n";
