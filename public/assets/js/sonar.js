"use strict";

(function () {
  const endpoint = '/api/sonar-metrics.php';
  const refreshButton = document.getElementById('sonar-refresh-button');
  const loader = document.getElementById('sonar-loader');
  const errorBox = document.getElementById('sonar-error');
  const projectNameEl = document.getElementById('sonar-project-name');
  const projectKeyEl = document.getElementById('sonar-project-key');
  const updatedAtEl = document.getElementById('sonar-updated-at');
  const qualityScoreEl = document.getElementById('sonar-quality-score');
  const statusPill = document.getElementById('sonar-status-pill');
  const coverageWarning = document.getElementById('sonar-coverage-warning');

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

  const fetchMetrics = async () => {
    errorBox.style.display = 'none';
    loader.style.display = 'block';
    refreshButton.disabled = true;
    try {
      const response = await fetch(endpoint, { cache: 'no-store' });
      const payload = await response.json();
      if (!response.ok || payload.error) {
        throw new Error(payload?.error || 'Respuesta no válida.');
      }
      renderMetrics(payload);
      renderCharts(payload);
    } catch (error) {
      errorBox.textContent = `No se pudieron obtener las métricas de SonarCloud: ${error instanceof Error ? error.message : error}`;
      errorBox.style.display = 'block';
    } finally {
      refreshButton.disabled = false;
      loader.style.display = 'none';
    }
  };

  refreshButton.addEventListener('click', fetchMetrics);
  document.addEventListener('DOMContentLoaded', fetchMetrics);
})();
