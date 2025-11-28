<?php
declare(strict_types=1);

use App\Infrastructure\Http\AuthGuards;

AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$activeTopAction = 'sentry';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Panel de Errores – Sentry</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Roboto:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/main.css" />
  <link rel="stylesheet" href="/assets/css/sonar.css" />
  <link rel="stylesheet" href="/assets/css/sentry.css" />
</head>

<body class="text-gray-200 min-h-screen bg-[#0b0d17]">

  <!-- HERO / HEADER -->
  <header class="app-hero app-hero--tech">
    <div class="app-hero__inner">
      <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div class="space-y-3 max-w-3xl">
          <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
          <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
            Observa errores y eventos en tiempo real desde Sentry sin salir del panel.
          </p>
          <p class="app-hero__meta text-base text-slate-300">
            Caché automática para evitar rate limits y mantener visibilidad aun sin conexión.
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
      <section class="sonar-panel sentry-panel section-lined space-y-8" aria-live="polite">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div class="space-y-1">
            <h2 class="sonar-hero-title text-4xl text-white leading-none">Ejecuta errores de demo</h2>
            <p class="text-slate-300 text-sm">Lanza errores falsos para probar el flujo Sentry → API → Panel.</p>
          </div>
          <div class="flex flex-col items-center gap-3 text-center">
            <div class="flex flex-wrap justify-center gap-3">
              <button id="sentry-refresh-button" class="btn btn-primary inline-flex items-center gap-2 mx-auto sentry-refresh-btn">
                <span>Actualizar</span>
              </button>
            </div>
            <div id="sentry-loader" class="sentry-loader" role="status" aria-live="polite" aria-atomic="true">Sincronizando métricas…</div>
            <div id="sentry-sync-dots" class="sentry-sync-dots">
              <span class="sentry-dot" aria-hidden="true"></span>
              <span class="sentry-dot" aria-hidden="true"></span>
              <span class="sentry-dot" aria-hidden="true"></span>
            </div>
          </div>
        </div>

        <div id="sentry-warning" class="sentry-alert" role="alert" aria-live="assertive" aria-atomic="true"></div>

        <article class="sonar-card space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="flex flex-col items-center gap-3">
              <button type="button" class="sentry-demo-btn sentry-demo-btn--red w-full text-center" data-sentry-test="500">Error 500</button>
              <button type="button" class="sentry-demo-btn sentry-demo-btn--red w-full text-center" data-sentry-test="zero">División por cero</button>
            </div>
            <div class="flex flex-col items-center gap-3">
              <button type="button" class="sentry-demo-btn sentry-demo-btn--amber w-full text-center" data-sentry-test="404">Error 404</button>
              <button type="button" class="sentry-demo-btn sentry-demo-btn--amber w-full text-center" data-sentry-test="method">Método inexistente</button>
            </div>
            <div class="flex flex-col items-center gap-3">
              <button type="button" class="sentry-demo-btn sentry-demo-btn--purple w-full text-center" data-sentry-test="db">DB Error</button>
              <button type="button" class="sentry-demo-btn sentry-demo-btn--purple w-full text-center" data-sentry-test="file">Archivo no encontrado</button>
            </div>
            <div class="flex flex-col items-center gap-3">
              <button type="button" class="sentry-demo-btn sentry-demo-btn--blue w-full text-center" data-sentry-test="timeout">Timeout</button>
              <button type="button" class="sentry-demo-btn sentry-demo-btn--blue w-full text-center" data-sentry-test="external">Servicio externo 503</button>
            </div>
          </div>
          <p id="sentry-test-status" class="text-sm text-slate-200" role="status" aria-live="polite" aria-atomic="true"></p>
        </article>

        <div class="sonar-grid metrics">
          <article class="sonar-card">
            <div class="flex items-center justify-between gap-2">
              <h4>Errores detectados</h4>
              <span id="sentry-source-badge" class="sentry-mini-badge" data-source="empty">—</span>
            </div>
            <p class="sonar-card-value text-white" id="sentry-total">—</p>
            <p class="sonar-card-sub">Issues abiertos devueltos por Sentry.</p>
          </article>
          <article class="sonar-card">
            <h4>Última actualización</h4>
            <p class="sonar-card-value text-sky-200" id="sentry-updated-at">—</p>
            <p class="sonar-card-sub">Fecha/hora del cache o de la respuesta en vivo.</p>
          </article>
          <article class="sonar-card">
            <h4>Estado</h4>
            <p class="sonar-card-value text-amber-100" id="sentry-status">Esperando datos</p>
            <p class="sonar-card-sub">Origen actual de la información mostrada.</p>
          </article>
        </div>

        <article class="sonar-card sentry-issues">
          <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
              <p class="uppercase tracking-[0.3em] text-sm text-slate-400">Sentry</p>
              <h3 class="text-2xl text-white">Top errores recientes</h3>
            </div>
            <p id="sentry-issues-count" class="text-slate-300 text-sm">— eventos</p>
          </header>

          <div id="sentry-issues-warning" class="sentry-inline-warning hidden" role="status" aria-live="polite" aria-atomic="true"></div>
          <div id="sentry-empty" class="sentry-empty-state hidden" role="status" aria-live="polite" aria-atomic="true">
            Sin datos de Sentry todavía. Buen trabajo, héroe. 🦸‍♂️
          </div>
          <div id="sentry-issues-list" class="sentry-issues-list"></div>
        </article>
      </section>
    </div>
  </main>

  <footer class="site-footer">
    <small>© creawebes 2025 · Clean Marvel Album</small>
  </footer>

  <script type="module" src="/assets/js/sentry.js"></script>
</body>

</html>
