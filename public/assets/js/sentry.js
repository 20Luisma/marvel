(() => {
  const totalEl = document.getElementById('sentry-total');
  const updatedEl = document.getElementById('sentry-updated-at');
  const statusEl = document.getElementById('sentry-status');
  const issuesList = document.getElementById('sentry-issues-list');
  const emptyState = document.getElementById('sentry-empty');
  const sourceIndicators = Array.from(document.querySelectorAll('[data-sentry-source]'));
  const refreshButton = document.getElementById('sentry-refresh-button');
  const loader = document.getElementById('sentry-loader');
  const alertBox = document.getElementById('sentry-warning');
  const inlineWarning = document.getElementById('sentry-issues-warning');
  const issuesCount = document.getElementById('sentry-issues-count');
  const testStatus = document.getElementById('sentry-test-status');
  const testButtons = document.querySelectorAll('[data-sentry-test]');
  const statusDots = document.querySelectorAll('#sentry-sync-dots .sentry-dot');

  const sourceLabels = {
    live: 'live',
    cache: 'cache',
    'cache-fallback': 'cache-fallback',
    empty: 'empty',
  };

  const PANEL_STATE = {
    ONLINE: 'online',
    OFFLINE: 'offline',
  };

  const STATUS_TEXT = {
    [PANEL_STATE.ONLINE]: 'En línea (datos frescos desde Sentry)',
    [PANEL_STATE.OFFLINE]: 'Fuera de línea (usando caché o sin datos)',
  };

  const setLoader = (visible) => {
    if (!loader) return;
    loader.style.display = visible ? 'block' : 'none';
  };

  const defaultLoaderText = loader?.textContent || 'Sincronizando métricas…';
  const setLoaderMessage = (message) => {
    if (!loader) return;
    loader.textContent = message;
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

  const formatTimestampForMetrics = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '—';
    }

    const pad = (num) => num.toString().padStart(2, '0');

    return `${pad(date.getHours())}:${pad(date.getMinutes())} · ${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
  };

  const applySource = (source) => {
    const normalized = sourceLabels[source] ?? 'empty';
    sourceIndicators.forEach((el) => {
      if (!el) return;
      el.dataset.source = normalized;
      el.textContent = normalized === 'live'
        ? 'live'
        : (normalized === 'empty' ? 'empty' : 'cache');
    });
  };

  const updateStatusDots = (state) => {
    if (!statusDots || statusDots.length === 0) return;
    statusDots.forEach((dot) => {
      dot.classList.toggle('is-green', state === PANEL_STATE.ONLINE);
      dot.classList.toggle('is-offline', state === PANEL_STATE.OFFLINE);
    });
  };

  const renderIssues = (issues) => {
    if (!issuesList || !emptyState || !issuesCount) return;

    issuesList.innerHTML = '';

    if (!issues || issues.length === 0) {
      emptyState.classList.remove('hidden');
      issuesList.classList.add('hidden');
      issuesCount.textContent = '0 eventos';
      return;
    }

    emptyState.classList.add('hidden');
    issuesList.classList.remove('hidden');
    issuesCount.textContent = `${issues.length} eventos`;

    // Render de tarjetas: cada evento aparece aunque Sentry lo agrupe bajo el mismo issue.
    issues.slice(0, 15).forEach((issue) => {
      const level = String(issue.level ?? 'info').toLowerCase();
      const title = issue.title ?? 'Sin título';
      const lastSeen = formatDate(issue.lastSeen ?? issue.last_seen ?? issue.last_seen_time ?? null);
      const eventsCount = issue.count ?? issue.userCount ?? issue.events ?? 0;

      const item = document.createElement('div');
      item.className = `sentry-issue level-${level}`;

      const titleEl = document.createElement('p');
      titleEl.className = 'sentry-issue__title';
      titleEl.textContent = title;

      const meta = document.createElement('div');
      meta.className = 'sentry-issue__meta';

      const levelPill = document.createElement('span');
      levelPill.className = 'sentry-issue__level';
      levelPill.textContent = level.toUpperCase();

      const eventsEl = document.createElement('span');
      eventsEl.textContent = `Eventos: ${eventsCount}`;

      const lastSeenEl = document.createElement('span');
      lastSeenEl.textContent = `Último: ${lastSeen}`;

      meta.append(levelPill, eventsEl, lastSeenEl);
      item.append(titleEl, meta);
      issuesList.appendChild(item);
    });
  };

  const determineState = (payload) => {
    const isLive = Boolean(payload?.ok) && payload?.source === 'live';
    return isLive ? PANEL_STATE.ONLINE : PANEL_STATE.OFFLINE;
  };

  const renderPayload = (payload) => {
    const source = payload?.source ?? 'empty';
    const state = determineState(payload);
    // Transformación: preferimos payload.events y degradamos a issues si llega el formato anterior.
    const data = payload?.data ?? payload ?? {};

    const issues = Array.isArray(data.events)
      ? data.events
      : (Array.isArray(data.issues) ? data.issues : []);
    const count = Number.isFinite(data.count)
      ? data.count
      : (Number.isFinite(data.errors) ? data.errors : issues.length);

    if (totalEl) totalEl.textContent = count || '0';
    if (state === PANEL_STATE.ONLINE && updatedEl) {
      updatedEl.textContent = formatTimestampForMetrics(payload?.last_update ?? data.cached_at ?? null);
    }

    if (statusEl) {
      statusEl.textContent = STATUS_TEXT[state];
      statusEl.classList.toggle('text-emerald-300', state === PANEL_STATE.ONLINE);
      statusEl.classList.toggle('text-amber-100', state === PANEL_STATE.OFFLINE);
    }

    updateStatusDots(state);
    applySource(source);
    renderIssues(issues);
  };

  const retryPlanMs = [600, 1200, 2000, 3200];

  const fetchWithRetries = async (url, options = {}, delays = retryPlanMs, onRetry = null) => {
    let lastError = null;
    const totalAttempts = delays.length + 1;

    for (let attempt = 1; attempt <= totalAttempts; attempt += 1) {
      try {
        const response = await fetch(url, options);
        if (response.ok) {
          return response;
        }

        const errorText = await response.text();
        lastError = new Error(`HTTP ${response.status}: ${errorText}`);
      } catch (error) {
        lastError = error;
      }

      if (attempt < totalAttempts) {
        if (typeof onRetry === 'function') {
          onRetry(attempt + 1, totalAttempts);
        }
        const delay = delays[attempt - 1] ?? 500;
        await new Promise((resolve) => setTimeout(resolve, delay));
      }
    }

    throw lastError ?? new Error('Error desconocido al conectar con Sentry.');
  };

  const fetchSentryMetrics = async () => {
    setLoader(true);
    setLoaderMessage(defaultLoaderText);
    setWarning(alertBox, '');
    setWarning(inlineWarning, '');

    try {
      const response = await fetchWithRetries(
        '/api/sentry-metrics.php',
        { cache: 'no-store' },
        retryPlanMs,
        (nextAttempt, total) => setLoaderMessage(`Reintentando conexión (${nextAttempt}/${total})…`)
      );
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const payload = await response.json();
      renderPayload(payload);

      if (payload?.warning) {
        setWarning(payload.source === 'cache-fallback' ? inlineWarning : alertBox, payload.warning);
      }
    } catch (error) {
      renderPayload({ source: 'empty', data: {} });
      setWarning(alertBox, 'No se pudo conectar con Sentry tras varios intentos. Intenta de nuevo en unos segundos.');
      console.error('Sentry fetch failed after retries', error);
    } finally {
      setLoader(false);
      setLoaderMessage(defaultLoaderText);
    }
  };

  const testErrorInfo = {
    '500': { label: 'Error 500', description: 'Falla interna simulada' },
    zero: { label: 'División por cero', description: 'Excepción aritmética' },
    '404': { label: 'Error 404', description: 'Recurso no encontrado' },
    method: { label: 'Método inexistente', description: 'Llamada a función ausente' },
    db: { label: 'DB Error', description: 'Fallo de base de datos simulado' },
    file: { label: 'Archivo no encontrado', description: 'Acceso a fichero inexistente' },
    timeout: { label: 'Timeout', description: 'Solicitud que expira' },
    external: { label: 'Servicio externo 503', description: 'Dependencia externa caída' },
  };

  const updateTestStatus = (value) => {
    if (!testStatus) return;
    testStatus.textContent = value;
  };

  const sentryTest = async (type) => {
    const details = testErrorInfo[type] ?? { label: `Error ${type}`, description: 'Evento demo' };
    updateTestStatus(`Enviando ${details.label} (${details.description})...`);

    try {
      const response = await fetch('/api/sentry-test.php?type=' + encodeURIComponent(type));
      const json = await response.json();
      if (response.ok && json?.ok) {
        updateTestStatus(`✔ ${details.label} enviado (${details.description}). Pulsa ACTUALIZAR para verlo en el panel.`);
      } else {
        updateTestStatus(`⚠ Hubo un problema enviando ${details.label}.`);
      }
    } catch (error) {
      console.error('Error al enviar el test de Sentry', error);
      updateTestStatus(`❌ Error en la solicitud al enviar ${details.label}.`);
    }
  };

  testButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const type = button.dataset.sentryTest;
      if (!type) {
        return;
      }
      sentryTest(type);
    });
  });

  if (refreshButton) {
    refreshButton.addEventListener('click', fetchSentryMetrics);
  }

  fetchSentryMetrics();
})();
