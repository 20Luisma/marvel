<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\AI\OpenAIComicGenerator;

// 1. Simulamos los datos que enviaría el formulario web
$heroesSimulados = [
    [
        'heroId' => '1009351', // ID real de Hulk
        'nombre' => 'Hulk',
        'contenido' => 'Un científico que se transforma en un monstruo verde gigante con fuerza ilimitada cuando se enfada.',
        'imagen' => 'http://i.annihil.us/u/prod/marvel/i/mg/5/a0/538615ca33ab0.jpg'
    ]
];

echo "\n🌐 Simulando petición desde la WEB...\n";
echo "----------------------------------------\n";
echo "👤 Usuario solicita cómic de: Hulk\n";

try {
    // 2. Instanciamos el generador (igual que hace el controlador de la web)
    $generator = new OpenAIComicGenerator();
    
    echo "🔄 Conectando con Microservicio AI...\n";
    
    // 3. Ejecutamos la generación
    $inicio = microtime(true);
    $result = $generator->generateComic($heroesSimulados);
    $tiempo = round(microtime(true) - $inicio, 2);

    echo "✅ ¡Respuesta recibida en {$tiempo}s!\n";
    echo "----------------------------------------\n";
    echo "📖 Título generado: " . $result['story']['title'] . "\n";
    echo "📝 Resumen: " . substr($result['story']['summary'], 0, 100) . "...\n";
    echo "----------------------------------------\n";
    
    // 4. Verificamos el log de tokens inmediatamente
    echo "\n📊 Verificando registro de tokens en el sistema...\n";
    $logFile = __DIR__ . '/storage/ai/tokens.log';
    $lines = file($logFile);
    $lastLine = end($lines);
    $data = json_decode($lastLine, true);
    
    if ($data && isset($data['total_tokens']) && $data['total_tokens'] > 0) {
        echo "🎉 ¡ÉXITO! Tokens registrados correctamente:\n";
        echo "   - Prompt Tokens: " . $data['prompt_tokens'] . "\n";
        echo "   - Completion Tokens: " . $data['completion_tokens'] . "\n";
        echo "   - TOTAL TOKENS: " . $data['total_tokens'] . "\n";
        echo "   - Modelo usado: " . $data['model'] . "\n";
    } else {
        echo "⚠️ Advertencia: La entrada se creó pero los tokens siguen en 0.\n";
        print_r($data);
    }

} catch (Exception $e) {
    echo "❌ Error en la simulación: " . $e->getMessage() . "\n";
}
echo "\n";
