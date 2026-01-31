<?php

declare(strict_types=1);

/**
 * NOTA DE SEGURIDAD (MODO DEMO):
 * Este endpoint es público de forma INTENCIONAL en este proyecto académico.
 * Permite a los usuarios limpiar y restaurar el estado de la demo para una nueva prueba.
 * 
 * Riesgos: DoS lógico por reseteos arbitrarios.
 * Mitigación: Riesgo aceptado con fines didácticos y de guía personal.
 * En un sistema productivo real, este acceso estaría protegido por autenticación,
 * tokens de un solo uso o restricciones de IP.
 */

use App\Shared\Application\UseCase\ResetDemoDataUseCase;
use Dotenv\Dotenv;

$rootPath = dirname(__DIR__, 2);
$vendorAutoload = $rootPath . '/vendor/autoload.php';

if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable($rootPath)->safeLoad();
} else {
    $envFile = $rootPath . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (!str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                $value = trim($value, " \t\n\r\0\x0B\"'");

                if ($name !== '') {
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                    putenv($name . '=' . $value);
                }
            }
        }
    }
}

/** @var array{albumRepository: object, heroRepository: object} $container */
$container = require_once $rootPath . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Método no permitido. Usa POST para este endpoint.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    /** @var \App\Albums\Domain\Repository\AlbumRepository $albumRepository */
    $albumRepository = $container['albumRepository'];
    /** @var \App\Heroes\Domain\Repository\HeroRepository $heroRepository */
    $heroRepository = $container['heroRepository'];

    $useCase = new ResetDemoDataUseCase($albumRepository, $heroRepository);
    $result = $useCase->execute();

    echo json_encode([
        'ok' => true,
        'message' => 'Datos de demo restaurados correctamente',
        'restored' => [
            'albums' => $result['albums'],
            'heroes' => $result['heroes'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error al restaurar datos: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
