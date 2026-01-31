<?php

declare(strict_types=1);

// Disable opcache to ensure code changes are reflected immediately
ini_set('opcache.enable', '0');
ini_set('opcache.enable_cli', '0');

if (!array_key_exists('MARVEL_RAW_BODY', $_SERVER)) {
    $raw = file_get_contents('php://input');
    $_SERVER['MARVEL_RAW_BODY'] = $raw === false ? '' : $raw;
}

use App\Security\Http\SecurityHeaders;
use App\Security\Http\CsrfMiddleware;
use App\Shared\Http\Router;
use App\Shared\Metrics\PrometheusMetrics;

require_once __DIR__ . '/../vendor/autoload.php';

// Aplica cabeceras de seguridad de forma centralizada.
// FASE 8.1 — CSP con nonces dinámicos
$cspNonce = \App\Security\Http\CspNonceGenerator::generate();
$_SERVER['CSP_NONCE'] = $cspNonce;
SecurityHeaders::apply($cspNonce);

if (!function_exists('route')) {
    /**
     * @param array<string, mixed> $container
     */
    function route(string $method, string $path, array $container): void
    {
        (new Router($container))->handle($method, $path);
    }
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($requestPath === '/' || $requestPath === '/index.php') {
    PrometheusMetrics::incrementRequests();
}

// CSRF middleware inicial para rutas POST de landing/home.
$csrfMiddleware = new CsrfMiddleware($GLOBALS['__clean_marvel_container']['security']['logger'] ?? null);
$csrfMiddleware->handle($requestPath);

if ($requestPath !== '/' && $requestPath !== '/index.php') {
    require_once __DIR__ . '/home.php';
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean Marvel Album | Intro</title>

    <!-- Fuente estilo Marvel -->
    <link href="https://fonts.googleapis.com/css2?family=Bangers&display=swap" rel="stylesheet">

    <?php $cspNonce = $_SERVER['CSP_NONCE'] ?? null; ?>
    <link rel="stylesheet" href="./assets/css/intro.css" <?= $cspNonce ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
</head>

<body>
    <div class="stars"></div>

    <main class="intro-shell" id="main-content" tabindex="-1" role="main">
        <p class="marvel-motto">"UN GRAN PODER CONLLEVA UNA GRAN RESPONSABILIDAD"</p>
        <div class="logo-frame">
            <img src="./assets/images/intromarvel.gif" alt="Intro Marvel Clean Album">
        </div>
    </main>

    <p class="master-note-big">
        PROYECTO FINAL DEL MÁSTER EN DESARROLLO DE IA - BIG SCHOOL 2025-2026
    </p>
    <p class="master-note-small">
        CREATED BY MARTIN PALLANTE · POWERED BY ALFRED (AI ASSISTANT)
    </p>

    <?php $cspNonce = $_SERVER['CSP_NONCE'] ?? null; ?>
    <script src="./assets/js/intro.js" defer<?= $cspNonce ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : '' ?>></script>

</body>

</html>