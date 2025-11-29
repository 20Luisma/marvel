<?php
declare(strict_types=1);
use App\Infrastructure\Http\AuthGuards;

AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$activeTopAction = 'security';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Security Dashboard – Clean Marvel Album</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Roboto:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/main.css" />
  <link rel="stylesheet" href="/assets/css/seguridad.css" />
</head>

<body class="text-gray-200 min-h-screen bg-[#0b0d17]">

  <!-- HERO / HEADER -->
  <header class="app-hero app-hero--tech">
    <div class="app-hero__inner">
      <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div class="space-y-3 max-w-3xl">
          <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
          <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
            Análisis automático de seguridad del servidor Marvel Album.
          </p>
          <p class="app-hero__meta text-base text-slate-300">
            Monitoreamos headers de seguridad y configuración usando SecurityHeaders.com y Mozilla Observatory.
          </p>
        </div>
      </div>
      <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
        <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
      </div>
    </div>
  </header>

  <main id="main-content" tabindex="-1" role="main" class="site-main">
    <div class="max-w-6xl mx-auto py-10 px-4">
      <section class="security-panel section-lined space-y-10" aria-live="polite">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <div class="space-y-2">
            <h2 class="sonar-hero-title text-4xl text-white">Marvel Security Board</h2>
            <p class="text-slate-300 text-sm" id="last-scan">Cargando...</p>
          </div>
          <div class="flex flex-col items-center gap-4 text-center">
            <button id="rescan-btn" class="btn btn-primary inline-flex items-center gap-2 mx-auto">
              <span>Actualizar</span>
            </button>
          </div>
        </div>

        <div class="security-grid">
          <div id="security-headers-card" class="security-card">
            <div class="skeleton-loader">
              <div class="skeleton-line"></div>
              <div class="skeleton-line"></div>
              <div class="skeleton-line short"></div>
            </div>
          </div>

          <div id="mozilla-card" class="security-card">
            <div class="skeleton-loader">
              <div class="skeleton-line"></div>
              <div class="skeleton-line"></div>
              <div class="skeleton-line short"></div>
            </div>
          </div>
        </div>

        <!-- Explicación de seguridad -->
        <div class="mt-8 pt-6 border-t border-slate-700/50">
          <h3 class="text-lg font-semibold text-white mb-3">¿Qué estamos analizando?</h3>
          <div class="space-y-4 text-sm text-slate-300 leading-relaxed">
            <div>
              <p class="text-cyan-400 font-semibold mb-1">🛡️ Headers de Seguridad HTTP</p>
              <p class="text-slate-400">
                Analizamos los headers de seguridad del servidor (CSP, HSTS, X-Frame-Options, etc.) que protegen contra ataques XSS, clickjacking y otras vulnerabilidades web. 
                Estos headers son la primera línea de defensa del navegador.
              </p>
            </div>
            <div>
              <p class="text-cyan-400 font-semibold mb-1">🔐 Certificado SSL/TLS</p>
              <p class="text-slate-400">
                Verificamos la validez del certificado SSL, algoritmos de cifrado, fecha de expiración y configuración HTTPS. 
                Un certificado válido garantiza que la comunicación entre el servidor y los usuarios está encriptada y es segura.
              </p>
            </div>
            <p class="text-xs text-slate-500 mt-4">
              Los datos se obtienen analizando directamente tu servidor en producción (<code class="text-cyan-400 bg-slate-900 px-1 rounded">https://iamasterbigschool.contenido.creawebes.com</code>). 
              Resultados actualizados cada 24 horas o manualmente con el botón "Actualizar".
            </p>
          </div>
        </div>
      </section>
    </div>
  </main>

  <!-- FOOTER -->
  <footer class="site-footer">
    <small>© creawebes 2025 · Clean Marvel Album</small>
  </footer>

  <?php $cspNonce = $_SERVER['CSP_NONCE'] ?? null; ?>
  <script src="/assets/js/seguridad.js" defer<?= $cspNonce ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : '' ?>></script>
</body>

</html>
