<?php

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

// Filtro de salida para ocultar la línea de fallback PDO en PHPUnit.
ob_start(static function (string $buffer): string {
    return preg_replace('/^.*Fallo al abrir conexión PDO.*$/m', '', $buffer) ?? $buffer;
});
register_shutdown_function(static function (): void {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
});

// Force predictable env for tests without touching production settings.
if (!isset($_ENV['APP_ENV']) || $_ENV['APP_ENV'] === '') {
    putenv('APP_ENV=test');
    $_ENV['APP_ENV'] = 'test';
    $_SERVER['APP_ENV'] = 'test';
}

// Avoid hitting external DB during tests; fall back to in-memory SQLite.
if (!isset($_ENV['DB_DSN']) || $_ENV['DB_DSN'] === '') {
    putenv('DB_DSN=sqlite::memory:');
    $_ENV['DB_DSN'] = 'sqlite::memory:';
    $_SERVER['DB_DSN'] = 'sqlite::memory:';
}
if (!isset($_ENV['DB_USER'])) {
    putenv('DB_USER=');
    $_ENV['DB_USER'] = '';
    $_SERVER['DB_USER'] = '';
}
if (!isset($_ENV['DB_PASSWORD'])) {
    putenv('DB_PASSWORD=');
    $_ENV['DB_PASSWORD'] = '';
    $_SERVER['DB_PASSWORD'] = '';
}

// Forzar repositorios en modo file durante tests para evitar intentos de PDO.
foreach (['ALBUMS_DRIVER', 'HEROES_DRIVER', 'ACTIVITY_DRIVER'] as $driverEnv) {
    putenv($driverEnv . '=file');
    $_ENV[$driverEnv] = 'file';
    $_SERVER[$driverEnv] = 'file';
}

// Silenciar avisos PHP en consola de PHPUnit y redirigirlos a un log temporal.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('error_log', sys_get_temp_dir() . '/phpunit-clean-marvel.log');

// Redirigir warning de PDO fallback a JSON para no ensuciar la salida de PHPUnit.
set_error_handler(static function (int $severity, string $message, ?string $file = null, ?int $line = null): bool {
    if (str_contains($message, 'Fallo al abrir conexión PDO')) {
        error_log(sprintf('[PDO_WARN][test] %s', $message));
        return true;
    }
    if (str_contains($message, 'Configuration Invalid') || str_contains($message, 'Configuración inválida')) {
        // Evita que ConfigValidator ensucie la salida de PHPUnit cuando se fuerza APP_ENV vacío en tests.
        error_log(sprintf('[CONFIG_WARN][test] %s', $message));
        return true;
    }
    return false;
});

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/OpenAITransportStub.php';
require_once __DIR__ . '/Heatmap/HttpHeatmapCurlStubs.php';
