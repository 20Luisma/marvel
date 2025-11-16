<?php

declare(strict_types=1);

use Src\Shared\Http\Router;

require_once __DIR__ . '/../vendor/autoload.php';

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

if ($requestPath !== '/' && $requestPath !== '/index.php') {
    require __DIR__ . '/home.php';
    exit;
}

$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com",
    "font-src 'self' https://fonts.gstatic.com https://r2cdn.perplexity.ai data:",
    "img-src 'self' data: blob: https:",
    "media-src 'self' data: blob: https:",
    "connect-src 'self' https: https://sentry.io http://localhost:8080 http://localhost:8081 http://localhost:8082",
    "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com",
    "frame-ancestors 'self'",
];

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: microphone=(), camera=(), geolocation=()');
header('Content-Security-Policy: ' . implode('; ', $csp));

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean Marvel Album | Intro</title>

    <!-- Fuente estilo Marvel -->
    <link href="https://fonts.googleapis.com/css2?family=Bangers&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="./assets/css/intro.css">
</head>

<body>
    <div class="stars"></div>

    <main class="intro-shell" id="intro">
        <div class="logo-frame">
            <img src="./assets/images/intromarvel.gif" alt="Intro Marvel Clean Album">
        </div>
    </main>

    <section class="login-shell" id="login-shell" aria-live="polite">

        <!-- ⭐ FRASE MARVEL ORIGINAL -->
        <p class="marvel-motto">"UN GRAN PODER CONLLEVA UNA GRAN RESPONSABILIDAD"</p>

        <!-- ⭐ BOX DEL FORMULARIO -->
        <div class="login-card">
            <h2>Inicia sesión de prueba</h2>

            <form id="login-form">
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24">
                            <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-3.33 0-7 1.67-7 3.5V20h14v-2.5C19 15.67 15.33 14 12 14Z" />
                        </svg>
                        <input id="username" type="email" placeholder="marvel@gmail.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24">
                            <path d="M17 9V7a5 5 0 0 0-10 0v2H5v12h14V9Zm-8-2a3 3 0 0 1 6 0v2H9Zm8 4v8H7v-8Z" />
                        </svg>
                        <input id="password" type="password" placeholder="marvel2025" required>
                    </div>
                </div>

                <div class="error" id="login-error"></div>

                <button type="submit" class="login-button">Entrar</button>
            </form>
        </div>

        <!-- ⭐ NUEVA FRASE: FUERA DEL BOX -->
        <p class="master-note-big">
            PROYECTO FINAL DEL MÁSTER EN DESARROLLO DE IA - BIG SCHOOL 2025
        </p>
        <p class="master-note-small">
            CREATED BY MARTIN PALLANTE · POWERED BY ALFRED (AI ASSISTANT)
        </p>
        <!-- ⭐ -->

    </section>

    <script src="./assets/js/intro.js" defer></script>

</body>

</html>
