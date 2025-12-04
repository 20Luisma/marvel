#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Script de verificación del TokenMetricsService
 * Valida que la resolución de rutas funciona correctamente
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Monitoring\TokenMetricsService;

echo "🔍 Verificación de Token Metrics Service\n";
echo str_repeat("=", 60) . "\n\n";

// Probar el servicio
$service = new TokenMetricsService();
$metrics = $service->getMetrics();

// Validar estructura
if (!isset($metrics['ok']) || $metrics['ok'] !== true) {
    echo "❌ ERROR: Métricas no válidas\n";
    exit(1);
}

// Mostrar estadísticas globales
echo "📊 ESTADÍSTICAS GLOBALES:\n";
echo str_repeat("-", 60) . "\n";
echo "Total de llamadas: " . $metrics['global']['total_calls'] . "\n";
echo "Total de tokens: " . number_format($metrics['global']['total_tokens']) . "\n";
echo "Promedio por llamada: " . $metrics['global']['avg_tokens_per_call'] . " tokens\n";
echo "Latencia promedio: " . $metrics['global']['avg_latency_ms'] . " ms\n";

if (isset($metrics['global']['estimated_cost_total'])) {
    echo "Costo estimado total: $" . $metrics['global']['estimated_cost_total'] . " USD\n";
    if (isset($metrics['global']['estimated_cost_total_eur'])) {
        echo "                      €" . $metrics['global']['estimated_cost_total_eur'] . " EUR\n";
    }
}

echo "\n";

// Mostrar por feature
echo "🎯 MÉTRICAS POR FEATURE:\n";
echo str_repeat("-", 60) . "\n";

$expectedFeatures = ['comic_generator', 'compare_heroes', 'marvel_agent'];
$foundFeatures = [];

foreach ($metrics['by_feature'] as $feature) {
    $name = $feature['feature'];
    $calls = $feature['calls'];
    $tokens = number_format($feature['total_tokens']);
    $avg = $feature['avg_tokens'];
    
    echo sprintf(
        "  %-20s %5d llamadas  %12s tokens  (%6.1f avg)\n",
        $name . ':',
        $calls,
        $tokens,
        $avg
    );
    
    $foundFeatures[] = $name;
}

echo "\n";

// Validar que se encontraron todas las features esperadas
echo "✅ VALIDACIÓN:\n";
echo str_repeat("-", 60) . "\n";

$allFound = true;
foreach ($expectedFeatures as $expected) {
    if (in_array($expected, $foundFeatures, true)) {
        echo "  ✓ {$expected} detectado correctamente\n";
    } else {
        echo "  ✗ {$expected} NO detectado (posible problema)\n";
        $allFound = false;
    }
}

echo "\n";

// Verificar archivos de log
echo "📁 ARCHIVOS DE LOG:\n";
echo str_repeat("-", 60) . "\n";

$mainLog = __DIR__ . '/../storage/ai/tokens.log';
$ragLogRelative = __DIR__ . '/../rag-service/storage/ai/tokens.log';
$ragLogAbsolute = '/home/REDACTED_SSH_USER/rag-service/storage/ai/tokens.log';

echo "Archivo principal: " . (file_exists($mainLog) ? "✓ Existe" : "✗ No existe") . "\n";
echo "  → {$mainLog}\n\n";

echo "Archivo RAG (relativo): " . (file_exists($ragLogRelative) ? "✓ Existe" : "✗ No existe") . "\n";
echo "  → {$ragLogRelative}\n\n";

echo "Archivo RAG (absoluto): " . (file_exists($ragLogAbsolute) ? "✓ Existe" : "✗ No existe") . "\n";
echo "  → {$ragLogAbsolute}\n\n";

// Mostrar llamadas recientes
echo "🕒 ÚLTIMAS 5 LLAMADAS:\n";
echo str_repeat("-", 60) . "\n";

$recentCount = min(5, count($metrics['recent_calls']));
for ($i = 0; $i < $recentCount; $i++) {
    $call = $metrics['recent_calls'][$i];
    $status = $call['success'] ? '✓' : '✗';
    echo sprintf(
        "  %s [%s] %-20s %6d tokens (%4d ms)\n",
        $status,
        substr($call['ts'], 0, 19),
        $call['feature'],
        $call['total_tokens'],
        $call['latency_ms']
    );
}

echo "\n";

// Resultado final
if ($allFound && $metrics['global']['total_calls'] > 0) {
    echo "✅ ÉXITO: TokenMetricsService funciona correctamente\n";
    echo "   Se detectaron " . count($foundFeatures) . "/" . count($expectedFeatures) . " features esperadas\n";
    exit(0);
} else {
    echo "⚠️  ADVERTENCIA: Algunas features no fueron detectadas\n";
    echo "   Esto podría indicar que los archivos de log no son accesibles\n";
    exit(2);
}
