<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Creawebes\Rag\Application\Clients\OpenAiEmbeddingClient;
use Creawebes\Rag\Infrastructure\Knowledge\MarvelAgentKnowledgeBase;

/**
 * Script de sincronización para Pinecone (Enterprise Mode)
 */

$bootstrap = require __DIR__ . '/../src/bootstrap.php';

$apiKey = $_ENV['PINECONE_API_KEY'] ?? getenv('PINECONE_API_KEY') ?: '';
$indexHost = $_ENV['PINECONE_INDEX_HOST'] ?? getenv('PINECONE_INDEX_HOST') ?: '';

if ($apiKey === '' || $indexHost === '') {
    echo "❌ Error: PINECONE_API_KEY o PINECONE_INDEX_HOST no configurados en .env\n";
    exit(1);
}

echo "🚀 Iniciando sincronización con Pinecone...\n";
echo "📍 Host: $indexHost\n\n";

// 1. Cargar la base de conocimientos local
$kbPath = __DIR__ . '/../storage/marvel_agent_kb.json';
$kb = new MarvelAgentKnowledgeBase($kbPath);
$entries = $kb->all();

if (empty($entries)) {
    echo "⚠️ La base de conocimientos está vacía.\n";
    exit(0);
}

$count = count($entries);
echo "📦 Encontradas $count entradas locales.\n";

$embeddingClient = new OpenAiEmbeddingClient();

foreach ($entries as $index => $entry) {
    echo "[" . ($index + 1) . "/$count] Procesando: " . $entry['title'] . "... ";
    
    try {
        // Generar embedding
        $text = trim($entry['title'] . "\n\n" . $entry['text']);
        $vector = $embeddingClient->embedText($text);
        
        if (empty($vector)) {
            echo "❌ Error al generar embedding.\n";
            continue;
        }

        // Subir a Pinecone
        $url = rtrim($indexHost, '/') . '/vectors/upsert';
        $payload = [
            'vectors' => [
                [
                    'id' => $entry['id'],
                    'values' => $vector,
                    'metadata' => [
                        'title' => $entry['title'],
                        'text' => $entry['text']
                    ]
                ]
            ]
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

        if ($httpCode === 200) {
            echo "✅ Sincronizado.\n";
        } else {
            echo "❌ Error HTTP $httpCode: $response\n";
        }

    } catch (Throwable $e) {
        echo "💥 Excepción: " . $e->getMessage() . "\n";
    }
}

echo "\n✨ Sincronización completada.\n";
