(() => {
  const totalEl = document.getElementById('sentry-total');
  const updatedEl = document.getElementById('sentry-updated-at');
  const statusEl = document.getElementById('sentry-status');
  const issuesList = document.getElementById('sentry-issues-list');
  const emptyState = document.getElementById('sentry-empty');
  const sourcePill = document.getElementById('sentry-source-pill');
  const sourceBadge = document.getElementById('sentry-source-badge');
  const refreshButton = document.getElementById('sentry-refresh-button');
  const loader = document.getElementById('sentry-loader');
  const alertBox = document.getElementById('sentry-warning');
  const inlineWarning = document.getElementById('sentry-issues-warning');
  const issuesCount = document.getElementById('sentry-issues-count');

  const statusText = {
    live: 'En línea (datos frescos desde Sentry)',
    cache: 'Usando última respuesta guardada',
    'cache-fallback': 'Usando última respuesta guardada',
    empty: 'Sin datos (Sentry aún no ha devuelto información)',
  };

  const sourceLabels = {
    live: 'live',
    cache: 'cache',
    'cache-fallback': 'cache-fallback',
    empty: 'empty',
  };

  const setLoader = (visible) => {
    if (!loader) return;
    loader.style.display = visible ? 'block' : 'none';
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

  const applySource = (source) => {
    const normalized = sourceLabels[source] ?? 'empty';
    [sourcePill, sourceBadge].forEach((el) => {
      if (!el) return;
      el.dataset.source = normalized;
      el.textContent = normalized === 'live'
        ? 'live'
        : (normalized === 'empty' ? 'empty' : 'cache');
    });
  };

  const renderIssues = (issues) => {
    if (!issuesList || !emptyState || !issuesCount) return;

    issuesList.innerHTML = '';

    if (!issues || issues.length === 0) {
      emptyState.classList.remove('hidden');
      issuesList.classList.add('hidden');
      issuesCount.textContent = '0 issues';
      return;
    }

    emptyState.classList.add('hidden');
    issuesList.classList.remove('hidden');
    issuesCount.textContent = `${issues.length} issues`;

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

  const renderPayload = (payload) => {
    const source = payload?.source ?? 'empty';
    const data = payload?.data ?? {};

    const issues = Array.isArray(data.issues) ? data.issues : [];
    const count = Number.isFinite(data.count) ? data.count : issues.length;

    if (totalEl) totalEl.textContent = count || '0';
    if (updatedEl) updatedEl.textContent = formatDate(data.cached_at ?? null);
    if (statusEl) statusEl.textContent = statusText[source] ?? statusText.empty;

    applySource(source);
    renderIssues(issues);
  };

  const fetchSentryMetrics = async () => {
    setLoader(true);
    setWarning(alertBox, '');
    setWarning(inlineWarning, '');

    try {
      const response = await fetch('/api/sentry-metrics.php', { cache: 'no-store' });
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
      setWarning(alertBox, 'No se pudo cargar Sentry en este momento.');
      console.error(error);
    } finally {
      setLoader(false);
    }
  };

  if (refreshButton) {
    refreshButton.addEventListener('click', fetchSentryMetrics);
  }

  fetchSentryMetrics();
})();
