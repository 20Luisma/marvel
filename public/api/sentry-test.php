<?php

declare(strict_types=1);

use RuntimeException;

if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false) {
    header('Location: /sentry');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/bootstrap.php';

$type = (string) ($_GET['type'] ?? 'generic');
$description = '';
$exception = null;

try {
    switch ($type) {
        case '500':
            $description = 'Demo 500: Error interno del servidor';
            $exception = new Exception($description);
            break;
        case '404':
            $description = 'Demo 404: Recurso no encontrado';
            $exception = new RuntimeException($description);
            break;
        case 'db':
            $description = 'Demo DB: Fallo en conexión';
            $exception = new PDOException($description);
            break;
        case 'timeout':
            $description = 'Demo Timeout: Servicio lento';
            sleep(1);
            $exception = new RuntimeException($description);
            break;
        case 'zero':
            $description = 'Demo: División por cero';
            $result = 1 / 0; // DivisionByZeroError
            break;
        case 'method':
            $description = 'Demo: Método inexistente';
            /** @var object $obj */
            $obj = new stdClass();
            $obj->missingMethod();
            break;
        case 'file':
            $description = 'Demo: Archivo no encontrado';
            $file = new SplFileObject('/tmp/sentry-demo-' . uniqid() . '.txt');
            $file->fread(1);
            break;
        case 'external':
            $description = 'Demo API: Servicio externo 503';
            $exception = new RuntimeException($description);
            break;
        default:
            $description = 'Demo: Error genérico';
            $exception = new Exception($description);
            break;
    }
} catch (Throwable $throwable) {
    $exception = $throwable;
}

if ($exception === null) {
    $exception = new Exception($description ?: 'Demo: Error genérico');
}

\Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($type): void {
    $scope->setTag('demo', 'true');
    $scope->setTag('demo_type', $type);
    $scope->setExtra('panel', 'Clean Marvel Album');
});

\Sentry\captureException($exception);

header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'ok' => true,
    'error' => $description,
    'sent' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
