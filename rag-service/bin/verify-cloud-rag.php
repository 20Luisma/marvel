<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar .env
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

use Creawebes\Rag\Application\Clients\OpenAiEmbeddingClient;

$apiKey = $_ENV['PINECONE_API_KEY'] ?? getenv('PINECONE_API_KEY');
$host = $_ENV['PINECONE_INDEX_HOST'] ?? getenv('PINECONE_INDEX_HOST');
$openAiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

if (!$apiKey || !$host || !$openAiKey) {
    echo "❌ Error: Faltan claves en el .env\n";
    exit(1);
}

echo "🔍 PROBANDO RECUPERACIÓN DESDE LA NUBE (PINECONE)\n";
echo "----------------------------------------------\n";

$pregunta = "Explícame la arquitectura técnica del proyecto";
echo "Pregunta de prueba: '$pregunta'\n";

// 1. Generar embedding real con OpenAI
echo "1. Generando vector con OpenAI... ";
$embeddingClient = new OpenAiEmbeddingClient();
$vector = $embeddingClient->embedText($pregunta);

if (empty($vector)) {
    echo "❌ Error al generar vector.\n";
    exit(1);
}
echo "✅ OK (" . count($vector) . " dimensiones)\n";

// 2. Consultar a la nube de Pinecone
echo "2. Consultando a Pinecone Cloud... ";
$url = rtrim($host, '/') . '/query';
$payload = [
    'vector' => $vector,
    'topK' => 3,
    'includeMetadata' => true,
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Api-Key: ' . $apiKey,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Error HTTP $httpCode\n";
    echo "Respuesta: $response\n";
    exit(1);
}
echo "✅ OK (Respuesta recibida)\n\n";

// 3. Mostrar resultados
$decoded = json_decode($response, true);
$matches = $decoded['matches'] ?? [];

echo "📄 RESULTADOS ENCONTRADOS EN LA NUBE:\n";
foreach ($matches as $index => $match) {
    $score = round($match['score'] * 100, 2);
    $title = $match['metadata']['title'] ?? 'Sin título';
    echo "[" . ($index + 1) . "] (Similitud: $score%) -> $title\n";
}

if (empty($matches)) {
    echo "⚠️ No se encontraron coincidencias en la nube.\n";
}

echo "\n✨ Si ves títulos de documentos aquí arriba, ¡el RAG está funcionando 100% desde la nube!\n";
