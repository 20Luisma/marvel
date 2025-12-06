"use strict";

(function () {
  const endpoint = '/api/sonar-metrics.php';
  const refreshButton = document.getElementById('sonar-refresh-button');
  const syncDots = document.querySelectorAll('.sonar-sync-dot');
  const errorBox = document.getElementById('sonar-error');
  const projectNameEl = document.getElementById('sonar-project-name');
  const projectKeyEl = document.getElementById('sonar-project-key');
  const updatedAtEl = document.getElementById('sonar-updated-at');
  const qualityScoreEl = document.getElementById('sonar-quality-score');
  const statusPill = document.getElementById('sonar-status-pill');
  const coverageWarning = document.getElementById('sonar-coverage-warning');
  const bundleJsTotalEl = document.getElementById('bundle-js-total');
  const bundleCssTotalEl = document.getElementById('bundle-css-total');
  const bundleJsCountEl = document.getElementById('bundle-js-count');
  const bundleCssCountEl = document.getElementById('bundle-css-count');
  const bundleGeneratedAtEl = document.getElementById('bundle-generated-at');
  const bundleTopList = document.getElementById('bundle-top-list');
  const bundleError = document.getElementById('bundle-error');

  const metricEls = {
    ncloc: document.getElementById('metric-ncloc'),
    code_smells: document.getElementById('metric-smells'),
    bugs: document.getElementById('metric-bugs'),
    vulnerabilities: document.getElementById('metric-vulns'),
    duplicated_code: document.getElementById('metric-dup'),
    complexity: document.getElementById('metric-complexity'),
  };

  let scoreChart;
  let alertsChart;
  let coverageChart;
  let syncInterval = null;
  let isSyncing = false;
  let isSynced = false;

  const ensureChartAvailable = () => {
    if (typeof Chart === 'undefined') {
      throw new Error('Chart.js no está disponible (bloqueado o no cargado).');
    }
  };

  const formatNumber = (value) => {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('es-MX').format(value);
  };

  const formatDate = (value) => {
    if (!value) return '—';
    try {
      return new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(value));
    } catch {
      return value;
    }
  };

  const setStatusPill = (score) => {
    const level = score >= 80 ? 'excellent' : score >= 60 ? 'acceptable' : 'alert';
    statusPill.dataset.level = level;
    statusPill.textContent = level === 'excellent'
      ? 'Excelente estado'
      : level === 'acceptable'
        ? 'Aceptable, con margen de mejora'
        : 'Necesita refactor y limpieza';
  };

  const renderCharts = (payload) => {
    ensureChartAvailable();
    const metrics = payload.metrics ?? {};

    if (scoreChart) scoreChart.destroy();
    const score = Number(payload.quality_score ?? 0);
    scoreChart = new Chart(document.getElementById('sonar-score-chart'), {
      type: 'doughnut',
      data: {
        labels: ['Score', 'Resto'],
        datasets: [{
          data: [score, Math.max(0, 100 - score)],
          backgroundColor: ['rgba(34,197,94,.85)', 'rgba(148,163,184,.35)'],
          borderWidth: 0,
        }],
      },
      options: {
        cutout: '70%',
        plugins: { legend: { position: 'bottom', labels: { color: '#cbd5f5' } } },
      },
    });

    if (alertsChart) alertsChart.destroy();
    alertsChart = new Chart(document.getElementById('sonar-alerts-chart'), {
      type: 'bar',
      data: {
        labels: ['Code Smells', 'Bugs', 'Vulnerabilidades'],
        datasets: [{
          data: [
            Number(metrics.code_smells?.value ?? 0),
            Number(metrics.bugs?.value ?? 0),
            Number(metrics.vulnerabilities?.value ?? 0),
          ],
          backgroundColor: ['rgba(236,72,153,.85)', 'rgba(248,113,113,.85)', 'rgba(59,130,246,.8)'],
          borderRadius: 14,
          barPercentage: 0.6,
        }],
      },
      options: {
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
          x: { beginAtZero: true, ticks: { color: '#cbd5f5' }, grid: { color: 'rgba(255,255,255,.08)' } },
          y: { ticks: { color: '#cbd5f5' }, grid: { display: false } },
        },
      },
    });

    if (coverageChart) coverageChart.destroy();
    const coverageValue = metrics.coverage?.value;
    const coverageCanvas = document.getElementById('sonar-coverage-chart');
    if (coverageValue === null || coverageValue === undefined || coverageValue === '') {
      coverageCanvas.style.display = 'none';
      coverageWarning.classList.remove('hidden');
    } else {
      coverageCanvas.style.display = 'block';
      coverageWarning.classList.add('hidden');
      coverageChart = new Chart(coverageCanvas, {
        type: 'doughnut',
        data: {
          labels: ['Cobertura', 'Sin cubrir'],
          datasets: [{
            data: [Number(coverageValue), Math.max(0, 100 - Number(coverageValue))],
            backgroundColor: ['rgba(56,189,248,.85)', 'rgba(248,113,113,.8)'],
            borderWidth: 0,
          }],
        },
        options: {
          cutout: '68%',
          plugins: { legend: { position: 'bottom', labels: { color: '#cbd5f5' } } },
        },
      });
    }
  };

  const renderMetrics = (payload) => {
    projectNameEl.textContent = payload.project_name
      ? `${payload.project_name.toUpperCase()} QUALITY BOARD`
      : 'Marvel Quality Board';
    projectKeyEl.textContent = payload.project_key ?? '—';
    updatedAtEl.textContent = formatDate(payload.updated_at);
    const score = Number(payload.quality_score ?? 0);
    qualityScoreEl.textContent = `${score}/100`;
    setStatusPill(score);

    const metrics = payload.metrics ?? {};
    Object.entries(metricEls).forEach(([key, el]) => {
      const value = metrics[key]?.value ?? null;
      if (key === 'duplicated_code' && value !== null) {
        el.textContent = `${Number(value).toFixed(1)}%`;
      } else {
        el.textContent = formatNumber(value);
      }
    });
  };

  // Indicador de sincronización: reutilizamos la lógica de Sentry con estados isSyncing/isSynced y pintamos 3 bolitas.
  const paintDots = (colors) => {
    syncDots.forEach((dot, idx) => {
      dot.style.backgroundColor = colors[idx] ?? '#f43f5e';
      dot.style.opacity = '0.9';
      dot.style.transform = 'scale(1)';
    });
  };

  const stopSyncAnimation = () => {
    if (syncInterval) {
      clearInterval(syncInterval);
      syncInterval = null;
    }
  };

  const setSyncState = (state) => {
    if (!syncDots || syncDots.length === 0) return;

    if (state === 'syncing') {
      isSyncing = true;
      isSynced = false;
      let step = 0;
      stopSyncAnimation();
      syncInterval = setInterval(() => {
        const palette = [
          ['#f43f5e', '#f59e0b', '#22c55e'],
          ['#f59e0b', '#22c55e', '#f43f5e'],
          ['#22c55e', '#f43f5e', '#f59e0b'],
        ];
        paintDots(palette[step % palette.length]);
        step += 1;
      }, 280);
      return;
    }

    stopSyncAnimation();

    if (state === 'synced') {
      isSynced = true;
      isSyncing = false;
      paintDots(['#22c55e', '#22c55e', '#22c55e']);
      return;
    }

    // idle
    isSyncing = false;
    isSynced = false;
    paintDots(['#f43f5e', '#f43f5e', '#f43f5e']);
  };

  const fetchMetricsWithRetry = async (maxAttempts = 3) => {
    let attempt = 0;
    let lastError = 'Respuesta no válida.';
    while (attempt < maxAttempts) {
      try {
        const response = await fetch(endpoint, { cache: 'no-store' });
        const payload = await response.json();
        if (!response.ok || payload.error) {
          throw new Error(payload?.error || `HTTP ${response.status}`);
        }
        return payload;
      } catch (err) {
        attempt += 1;
        lastError = err instanceof Error ? err.message : 'Respuesta no válida.';
        if (attempt >= maxAttempts) {
          throw new Error(lastError);
        }
        await new Promise((resolve) => setTimeout(resolve, 200));
      }
    }
    throw new Error(lastError);
  };

  const fetchMetrics = async () => {
    errorBox.style.display = 'none';
    setSyncState('syncing');
    refreshButton.disabled = true;
    let succeeded = false;
    try {
      const payload = await fetchMetricsWithRetry(3);
      renderMetrics(payload);
      renderCharts(payload);
      succeeded = true;
    } catch (error) {
      errorBox.textContent = `No se pudieron obtener las métricas de SonarCloud: ${error instanceof Error ? error.message : error}`;
      errorBox.style.display = 'block';
    } finally {
      refreshButton.disabled = false;
      setSyncState(succeeded ? 'synced' : 'idle');
    }
  };

  const renderBundleSize = (data) => {
    if (!bundleJsTotalEl || !bundleCssTotalEl || !bundleTopList || !bundleGeneratedAtEl) {
      return;
    }

    const totals = data?.totals ?? {};
    const top = Array.isArray(data?.top) ? data.top : [];

    bundleGeneratedAtEl.textContent = formatDate(data?.generatedAt ?? data?.generated_at ?? null);
    bundleJsTotalEl.textContent = totals.js?.human ?? '—';
    bundleCssTotalEl.textContent = totals.css?.human ?? '—';
    bundleJsCountEl.textContent = `${totals.js?.count ?? 0} archivos`;
    bundleCssCountEl.textContent = `${totals.css?.count ?? 0} archivos`;

    bundleTopList.innerHTML = '';
    if (top.length === 0) {
      const li = document.createElement('li');
      li.textContent = 'No hay archivos JS/CSS registrados.';
      li.className = 'text-slate-500';
      bundleTopList.appendChild(li);
    } else {
      top.forEach((item) => {
        const li = document.createElement('li');
        li.className = 'flex items-center justify-between gap-2 border-b border-slate-800/60 pb-1 last:border-none';
        const name = document.createElement('span');
        name.textContent = item.path || '—';
        const size = document.createElement('span');
        size.className = 'text-slate-300 font-semibold';
        size.textContent = item.human || `${item.bytes ?? 0} B`;
        li.append(name, size);
        bundleTopList.appendChild(li);
      });
    }
  };

  const loadBundleSize = async () => {
    if (!bundleTopList || !bundleError) return;

    bundleError.classList.add('hidden');
    bundleError.textContent = '';
    try {
      const pageUrl = typeof window !== 'undefined' ? new URL(window.location.href) : null;
      const basePath = (typeof window !== 'undefined' && window.BUNDLE_BASE_PATH)
        ? String(window.BUNDLE_BASE_PATH).replace(/\/$/, '')
        : '';

      const pathSegments = (typeof window !== 'undefined' ? window.location.pathname.split('/') : []).filter(Boolean);
      const prefix = pathSegments.length > 0 ? `/${pathSegments[0]}` : '';

      const candidates = new Set();
      if (pageUrl) {
        candidates.add(new URL('assets/bundle-size.json', pageUrl).toString());
        candidates.add(new URL('/assets/bundle-size.json', pageUrl.origin).toString());
      }
      if (prefix) {
        candidates.add(`${prefix}/assets/bundle-size.json`);
        candidates.add(`${prefix}/public/assets/bundle-size.json`);
      }
      candidates.add(`${basePath}/assets/bundle-size.json`);
      candidates.add('/public/assets/bundle-size.json');
      candidates.add('/assets/bundle-size.json');
      candidates.add('assets/bundle-size.json');

      let payload = null;
      let lastStatus = '';

      for (const url of candidates) {
        const res = await fetch(url, { cache: 'no-store' });
        if (res.ok) {
          payload = await res.json();
          break;
        }
        lastStatus = `HTTP ${res.status}`;
      }

      if (!payload) {
        throw new Error(lastStatus || 'No encontrado');
      }

      renderBundleSize(payload);
    } catch (err) {
      bundleError.textContent = `Bundle size no disponible: ${err instanceof Error ? err.message : 'error desconocido'}`;
      bundleError.classList.remove('hidden');
    }
  };

  refreshButton.addEventListener('click', fetchMetrics);
  document.addEventListener('DOMContentLoaded', () => {
    setSyncState('idle');
    fetchMetrics().catch(() => setSyncState('idle'));
    loadBundleSize().catch(() => {});
  });
})();
