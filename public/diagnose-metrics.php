<?php

declare(strict_types=1);

// Habilitar visualización de errores para depuración
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use App\Monitoring\TokenMetricsService;

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');

echo "<style>body { font-family: sans-serif; background: #1a1a1a; color: #fff; padding: 20px; } pre { background: #333; padding: 10px; overflow: auto; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #555; padding: 8px; text-align: left; } th { background: #333; } .ok { color: #4ade80; } .error { color: #f87171; }</style>";

echo "<h1>🕵️‍♂️ Diagnóstico de Métricas de Tokens</h1>";

echo "<h2>1. Información del Entorno</h2>";
echo "<ul>";
echo "<li><strong>Directorio Actual (__DIR__):</strong> " . __DIR__ . "</li>";
echo "<li><strong>Usuario Ejecutando PHP:</strong> " . get_current_user() . "</li>";
echo "<li><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "</ul>";

try {
    $service = new TokenMetricsService();
    $metrics = $service->getMetrics();
    
    echo "<h2>2. Resultado de TokenMetricsService</h2>";
    
    if (isset($metrics['debug'])) {
        echo "<h3>🔍 Análisis de Rutas de Log (Debug Info)</h3>";
        $debug = $metrics['debug'];
        $rag = $debug['rag_log_path_resolution'] ?? [];
        
        echo "<table>";
        echo "<tr><th>Método</th><th>Ruta Probada</th><th>Existe?</th></tr>";
        
        // Env Var
        echo "<tr><td>ENV: RAG_LOG_PATH</td><td>" . htmlspecialchars((string)($rag['env_var'] ?? 'No definida')) . "</td><td>-</td></tr>";
        
        // Relative
        $relPath = $rag['relative_path'] ?? '';
        $relExists = $rag['relative_exists'] ?? false;
        echo "<tr><td>Relativa (Local)</td><td>" . htmlspecialchars((string)$relPath) . "</td>";
        echo "<td class='" . ($relExists ? 'ok' : 'error') . "'>" . ($relExists ? '✅ SÍ' : '❌ NO') . "</td></tr>";
        
        // Sibling
        $sibPath = $rag['sibling_path'] ?? '';
        $sibExists = $rag['sibling_exists'] ?? false;
        echo "<tr><td>Hermano (Sibling)</td><td>" . htmlspecialchars((string)$sibPath) . "</td>";
        echo "<td class='" . ($sibExists ? 'ok' : 'error') . "'>" . ($sibExists ? '✅ SÍ' : '❌ NO') . "</td></tr>";
        
        // Hosting Hardcoded
        $hostPath = $rag['hosting_path'] ?? '';
        $hostExists = $rag['hosting_exists'] ?? false;
        echo "<tr><td>Hosting (Hardcoded)</td><td>" . htmlspecialchars((string)$hostPath) . "</td>";
        echo "<td class='" . ($hostExists ? 'ok' : 'error') . "'>" . ($hostExists ? '✅ SÍ' : '❌ NO') . "</td></tr>";
        
        echo "</table>";
        
        echo "<h3>🎯 Ruta Final Resuelta</h3>";
        if ($rag['resolved_path']) {
             echo "<p class='ok' style='font-size: 1.2em'><strong>" . htmlspecialchars((string)$rag['resolved_path']) . "</strong></p>";
             
             // Intentar leer permisos y contenido
             echo "<h4>Prueba de Lectura:</h4>";
             if (is_readable((string)$rag['resolved_path'])) {
                 echo "<p class='ok'>✅ El archivo es legible por PHP.</p>";
                 $lines = file((string)$rag['resolved_path']);
                 echo "<p>Líneas encontradas: " . count($lines) . "</p>";
             } else {
                 echo "<p class='error'>❌ El archivo existe pero NO es legible (Revisar Permisos).</p>";
             }
             
        } else {
             echo "<p class='error' style='font-size: 1.2em'><strong>❌ Ninguna ruta funcionó.</strong></p>";
        }

    } else {
        echo "<p class='error'>⚠️ No se encontró información de depuración. Asegúrate de haber subido el archivo TokenMetricsService.php actualizado.</p>";
    }
    
    echo "<h2>3. Respuesta JSON Completa</h2>";
    echo "<pre>" . htmlspecialchars(json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";

} catch (Exception $e) {
    echo "<h2 class='error'>🔥 Error Crítico</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
