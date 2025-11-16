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
            <div id="sentry-sync-dots" class="sentry-sync-dots">
              <span class="sentry-dot"></span>
              <span class="sentry-dot"></span>
              <span class="sentry-dot"></span>
            </div>
          </div>
        </div>

        <div id="sentry-warning" class="sentry-alert"></div>

        <article class="sonar-card space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="flex flex-col items-center gap-3">
              <button class="sentry-demo-btn sentry-demo-btn--red w-full text-center" onclick="sentryTest('500')">Error 500</button>
              <button class="sentry-demo-btn sentry-demo-btn--red w-full text-center" onclick="sentryTest('zero')">División por cero</button>
            </div>
            <div class="flex flex-col items-center gap-3">
              <button class="sentry-demo-btn sentry-demo-btn--amber w-full text-center" onclick="sentryTest('404')">Error 404</button>
              <button class="sentry-demo-btn sentry-demo-btn--amber w-full text-center" onclick="sentryTest('method')">Método inexistente</button>
            </div>
            <div class="flex flex-col items-center gap-3">
              <button class="sentry-demo-btn sentry-demo-btn--purple w-full text-center" onclick="sentryTest('db')">DB Error</button>
              <button class="sentry-demo-btn sentry-demo-btn--purple w-full text-center" onclick="sentryTest('file')">Archivo no encontrado</button>
            </div>
            <div class="flex flex-col items-center gap-3">
              <button class="sentry-demo-btn sentry-demo-btn--blue w-full text-center" onclick="sentryTest('timeout')">Timeout</button>
              <button class="sentry-demo-btn sentry-demo-btn--blue w-full text-center" onclick="sentryTest('external')">Servicio externo 503</button>
            </div>
          </div>
          <p id="sentry-test-status" class="text-sm text-slate-200"></p>
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

  <script>
    // Render y llamadas de métricas Sentry (similar a SonarCloud, con cache y estados)
    (function () {
      const totalEl = document.getElementById('sentry-total');
      const updatedEl = document.getElementById('sentry-updated-at');
      const statusEl = document.getElementById('sentry-status');
      const syncDots = document.querySelectorAll('.sentry-dot');
      let syncState = 'idle';
      const sourceBadgeEl = document.getElementById('sentry-source-badge');
      const issuesList = document.getElementById('sentry-issues-list');
      const emptyState = document.getElementById('sentry-empty');
      const refreshButton = document.getElementById('sentry-refresh-button');
      const loader = document.getElementById('sentry-loader');
      const alertBox = document.getElementById('sentry-warning');
      const inlineWarning = document.getElementById('sentry-issues-warning');
      const issuesCount = document.getElementById('sentry-issues-count');

      // Render y cacheo: toda la llamada a la API de Sentry se hace vía /api/sentry-metrics.php
      const setLoader = (visible) => {
        if (visible) {
          syncState = 'syncing';
        } else if (syncState !== 'synced') {
          syncState = 'idle';
        }
        updateDots();
        if (loader) {
          loader.style.display = 'none';
        }
      };

      const setWarning = (element, message) => {
        if (!element) return;
        if (message) {
          element.textContent = message;
          element.classList.remove('hidden');
          element.style.display = element === alertBox ? 'block' : 'flex';
        } else {
          element.textContent = '';
          element.classList.add('hidden');
          element.style.display = 'none';
        }
      };

      const formatDate = (value) => {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('es-ES');
      };

      const updateDots = () => {
        if (!syncDots || syncDots.length === 0) return;
        syncDots.forEach((dot, idx) => {
          dot.style.animationDelay = `${idx * 0.18}s`;
          dot.classList.remove('animating', 'is-green');
          if (syncState === 'syncing') {
            dot.classList.add('animating');
          } else if (syncState === 'synced') {
            dot.classList.add('is-green');
          }
        });
      };

      const renderIssues = (issues) => {
        if (!issuesList || !emptyState || !issuesCount) return;

        issuesList.innerHTML = '';

        if (!issues || issues.length === 0) {
          emptyState.classList.remove('hidden');
          issuesList.classList.add('hidden');
          issuesCount.textContent = '0 eventos';
          updateDeleteSelectedVisibility();
          return;
        }

        emptyState.classList.add('hidden');
        issuesList.classList.remove('hidden');
        issuesCount.textContent = `${issues.length} eventos`;

        // Render de tarjetas: pintamos cada evento reciente aunque pertenezca al mismo issue agrupado en Sentry.
        issues.forEach((issue) => {
          const level = String(issue.level ?? 'info').toLowerCase();
          const title = issue.title ?? 'Sin título';
          const lastSeen = formatDate(issue.last_seen ?? issue.lastSeen ?? null);
          const permalink = issue.url ?? '#';
          const shortId = issue.short_id ?? '';
          const issueId = issue.id ?? '';

          const item = document.createElement('div');
          item.className = `sentry-issue level-${level} sentry-issue-card`;
          item.setAttribute('data-issue-id', issueId);

          const titleEl = document.createElement('p');
          titleEl.className = 'sentry-issue__title';
          titleEl.textContent = title + (shortId ? ` (${shortId})` : '');

          const meta = document.createElement('div');
          meta.className = 'sentry-issue__meta';

          const levelPill = document.createElement('span');
          levelPill.className = 'sentry-issue__level';
          levelPill.textContent = level.toUpperCase();

          const lastSeenEl = document.createElement('span');
          lastSeenEl.textContent = `Último: ${lastSeen}`;

          meta.append(levelPill, lastSeenEl);
          item.append(titleEl, meta);
          issuesList.appendChild(item);
        });

        // No acciones de borrado ni selección
      };

      const renderPayload = (payload) => {
        const source = payload?.source ?? 'empty';
        // Transformación: priorizamos payload.events (eventos individuales) y dejamos issues como alias legacy.
        const events = Array.isArray(payload?.events)
          ? payload.events
          : (Array.isArray(payload?.issues) ? payload.issues : []);
        const errors = Number.isFinite(payload?.errors) ? payload.errors : events.length;
        const lastUpdate = payload?.last_update ?? null;
        const statusText = payload?.status ?? 'EMPTY';

        if (totalEl) totalEl.textContent = errors;
        if (updatedEl) updatedEl.textContent = formatDate(lastUpdate);
        if (statusEl) statusEl.textContent = statusText;
        if (sourceBadgeEl) {
          const normalized = source === 'live' ? 'live' : (source === 'cache' ? 'cache' : 'empty');
          sourceBadgeEl.dataset.source = normalized;
          sourceBadgeEl.textContent = normalized.toUpperCase();
        }

        renderIssues(events);
        syncState = source === 'live' ? 'synced' : 'idle';
        updateDots();
      };

      const fetchSentryWithRetry = async (maxAttempts = 2) => {
        let attempt = 0;
        while (attempt < maxAttempts) {
          try {
            const response = await fetch('/api/sentry-metrics.php', { cache: 'no-store' });
            if (!response.ok) {
              throw new Error(`HTTP ${response.status}`);
            }
            return await response.json();
          } catch (error) {
            attempt++;
            if (attempt >= maxAttempts) {
              throw error;
            }
            await new Promise((resolve) => setTimeout(resolve, 200));
          }
        }
      };

      const loadSentryMetrics = async () => {
        setLoader(true);
        setWarning(alertBox, '');
        setWarning(inlineWarning, '');

        try {
          const payload = await fetchSentryWithRetry(3);
          renderPayload(payload);

          if (payload?.message) {
            setWarning(alertBox, payload.message);
          }
        } catch (error) {
          renderPayload({ source: 'empty', errors: 0, issues: [] });
          setWarning(alertBox, 'No se pudo cargar Sentry en este momento.');
          console.error(error);
          syncState = 'idle';
          updateDots();
        } finally {
          setLoader(false);
        }
      };

      if (refreshButton) {
        refreshButton.addEventListener('click', loadSentryMetrics);
      }

      updateDots();
      loadSentryMetrics();

      function bindSentryIssueActions() {
      }

      function updateDeleteSelectedVisibility() {}
    })();

    // Disparador de errores demo hacia Sentry (modo prueba)
    async function sentryTest(type) {
      const status = document.getElementById('sentry-test-status');
      const errorInfo = {
        '500': { label: 'Error 500', description: 'Falla interna simulada' },
        zero: { label: 'División por cero', description: 'Excepción aritmética' },
        '404': { label: 'Error 404', description: 'Recurso no encontrado' },
        method: { label: 'Método inexistente', description: 'Llamada a función ausente' },
        db: { label: 'DB Error', description: 'Fallo de base de datos simulado' },
        file: { label: 'Archivo no encontrado', description: 'Acceso a fichero inexistente' },
        timeout: { label: 'Timeout', description: 'Solicitud que expira' },
        external: { label: 'Servicio externo 503', description: 'Dependencia externa caída' },
      };
      const details = errorInfo[type] ?? { label: `Error ${type}`, description: 'Evento demo' };

      if (status) {
        status.textContent = `Enviando ${details.label} (${details.description})...`;
      }
      try {
        const res = await fetch('/api/sentry-test.php?type=' + encodeURIComponent(type));
        const json = await res.json();
        if (json.ok) {
          status.textContent = `✔ ${details.label} enviado (${details.description}). Pulsa ACTUALIZAR para verlo en el panel.`;
        } else {
          status.textContent = `⚠ Hubo un problema enviando ${details.label}.`;
        }
      } catch (e) {
        if (status) {
          status.textContent = `❌ Error en la solicitud al enviar ${details.label}.`;
        }
      }
    }
  </script>
</body>

</html>
