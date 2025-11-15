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
  <header class="app-hero">
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
        <div class="flex items-center gap-3 ml-auto">
          <a href="/albums" class="btn app-hero__cta app-hero__cta-equal app-hero__cta--flat">Inicio</a>
          <a href="/comic" class="btn app-hero__cta app-hero__cta-equal app-hero__cta--flat">Crear Cómic</a>
          <a href="/oficial-marvel" class="btn app-hero__cta app-hero__cta-equal">Oficial Marvel</a>
          <a href="/sonar" class="btn app-hero__cta app-hero__cta-equal app-hero__cta--github app-hero__cta--flat">SonarCloud</a>
          <a href="/sentry" class="btn app-hero__cta app-hero__cta-equal is-active app-hero__cta--github" aria-current="page">Sentry</a>
          <a href="/panel-github" class="btn app-hero__cta app-hero__cta-equal app-hero__cta--github">GitHub PRs</a>
          <a id="btn-readme" href="/readme" class="btn app-hero__cta app-hero__cta-equal btn-readme app-hero__cta--github">
            <span>README</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <main class="site-main">
    <div class="max-w-6xl mx-auto py-10 px-4">
      <section class="sonar-panel sentry-panel space-y-8" aria-live="polite">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <div class="space-y-2">
            <p class="uppercase tracking-[0.3em] text-sm text-slate-400">Sentry</p>
            <h2 class="sonar-hero-title text-4xl text-white">Sentry Error Board</h2>
            <p class="text-slate-300 text-sm">Monitoriza issues, niveles y últimas apariciones sin salir de Clean Marvel.</p>
          </div>
          <div class="flex flex-col items-center gap-4 text-center">
            <span id="sentry-source-pill" class="sentry-badge" data-source="empty">Esperando datos</span>
            <div class="flex flex-wrap justify-center gap-3">
              <button id="sentry-refresh-button" class="btn btn-primary inline-flex items-center gap-2 mx-auto">
                <span>Actualizar</span>
              </button>
            </div>
            <p id="sentry-loader" class="sentry-loader uppercase tracking-[0.4em]">Sincronizando con Sentry…</p>
          </div>
        </div>

        <div id="sentry-warning" class="sentry-alert"></div>

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
            <p id="sentry-issues-count" class="text-slate-300 text-sm">— issues</p>
          </header>

          <div id="sentry-issues-warning" class="sentry-inline-warning hidden"></div>
          <div id="sentry-empty" class="sentry-empty-state hidden">
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

  <script src="/assets/js/sentry.js" defer></script>
</body>

</html>
