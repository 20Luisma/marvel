"use strict";

(function () {
  const endpoint = '/api/performance-marvel.php';
  const resultContainer = document.getElementById('performance-result');
  const stateContainer = document.getElementById('performance-state');
  const refreshButton = document.getElementById('performance-refresh-button');

  if (!resultContainer || !stateContainer) {
    return;
  }

  const escapeHtml = (value) => {
    const str = String(value ?? '');
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  const ensureLoaderStyles = () => {
    if (document.getElementById('performance-loader-style')) {
      return;
    }
    const style = document.createElement('style');
    style.id = 'performance-loader-style';
    style.textContent = `
      .perf-loader {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-right: 8px;
      }
      .perf-loader span {
        width: 8px;
        height: 8px;
        border-radius: 9999px;
        background: linear-gradient(120deg, #22d3ee, #7c3aed);
        opacity: 0.3;
        animation: perf-bounce 1s infinite ease-in-out;
      }
      .perf-loader span:nth-child(2) { animation-delay: 0.15s; }
      .perf-loader span:nth-child(3) { animation-delay: 0.3s; }
      @keyframes perf-bounce {
        0%, 80%, 100% { opacity: 0.3; transform: translateY(0); }
        40% { opacity: 1; transform: translateY(-6px); }
      }
    `;
    document.head.appendChild(style);
  };

  const setState = (message, isError = false, withLoader = false) => {
    stateContainer.classList.toggle('sonar-alert--error', isError);
    if (withLoader) {
      ensureLoaderStyles();
      stateContainer.innerHTML = `<span class="perf-loader" aria-hidden="true"><span></span><span></span><span></span></span><span>${escapeHtml(message)}</span>`;
    } else {
      stateContainer.textContent = message;
    }
    stateContainer.style.display = message ? 'block' : 'none';
  };

  const parseNumeric = (value) => {
    if (typeof value !== 'string' && typeof value !== 'number') {
      return null;
    }
    const normalized = String(value).replace(/[^0-9.,]/g, '').replace(',', '.');
    const parsed = parseFloat(normalized);
    return Number.isNaN(parsed) ? null : parsed;
  };

  const computeAverage = (pages, extractor) => {
    if (!Array.isArray(pages) || pages.length === 0) {
      return null;
    }
    const numbers = pages
      .map(extractor)
      .filter((value) => typeof value === 'number' && !Number.isNaN(value));
    if (numbers.length === 0) {
      return null;
    }
    return numbers.reduce((sum, value) => sum + value, 0) / numbers.length;
  };

  const renderStatCard = (title, value, subtitle, accentClass) => `
    <article class="rounded-2xl border border-slate-700/80 bg-gradient-to-br from-slate-900/70 to-slate-950/70 p-4 shadow shadow-black/40 ${accentClass}">
      <p class="text-xs uppercase tracking-[0.3em] text-slate-400">${title}</p>
      <p class="text-3xl font-semibold text-white">${value}</p>
      <p class="text-xs uppercase tracking-[0.3em] text-slate-500 mt-1">${subtitle}</p>
    </article>
  `;

  const renderResumenPerformance = (payload) => {
    const pages = Array.isArray(payload.paginas) ? payload.paginas.filter((page) => page.estado === 'exito') : [];
    const avgScore = computeAverage(pages, (page) => parseNumeric(page.performance?.score) ?? null);
    const avgLcp = computeAverage(pages, (page) => parseNumeric(page.performance?.lcp));
    const avgFcp = computeAverage(pages, (page) => parseNumeric(page.performance?.fcp));
    const avgCls = computeAverage(pages, (page) => parseNumeric(page.performance?.cls));
    const avgTbt = computeAverage(pages, (page) => parseNumeric(page.performance?.tbt));

    const summary = [
      renderStatCard('Páginas analizadas', payload.total_paginas ?? '—', 'Urls evaluadas', 'border-blue-500/70'),
      renderStatCard('Score medio', avgScore !== null ? `${Math.round(avgScore)}/100` : '—', 'Promedio (0-100)', 'border-amber-400/70'),
      renderStatCard('LCP medio', avgLcp !== null ? `${avgLcp.toFixed(2)} s` : '—', 'Largest Contentful Paint', 'border-rose-500/70'),
      renderStatCard('FCP medio', avgFcp !== null ? `${avgFcp.toFixed(2)} s` : '—', 'First Contentful Paint', 'border-sky-500/70'),
      renderStatCard('CLS medio', avgCls !== null ? avgCls.toFixed(2) : '—', 'Cumulative Layout Shift', 'border-emerald-400/70'),
      renderStatCard('TBT/INP medio', avgTbt !== null ? `${avgTbt.toFixed(2)} ms` : '—', 'Total Blocking Time / INP', 'border-purple-500/70'),
    ];

    return `
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        ${summary.join('')}
      </div>
    `;
  };

  const scoreColor = (score) => {
    if (typeof score !== 'number') {
      return 'border-slate-500 text-slate-100';
    }
    if (score >= 90) {
      return 'border-emerald-500 text-emerald-300';
    }
    if (score >= 50) {
      return 'border-amber-500 text-amber-300';
    }
    return 'border-rose-500 text-rose-300';
  };

  const renderOpportunities = (opportunities) => {
    if (!Array.isArray(opportunities) || opportunities.length === 0) {
      return '<p class="text-sm text-slate-400">Sin oportunidades adicionales.</p>';
    }

    return `
      <div class="space-y-3 mt-3">
        ${opportunities
          .map(
            (item) => `
              <article class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                <p class="text-white font-semibold">${escapeHtml(item.titulo)}</p>
                <p class="text-slate-300 text-sm mt-1">${escapeHtml(item.descripcion)}</p>
                ${item.ahorro ? `<p class="text-xs uppercase tracking-[0.3em] text-slate-400 mt-2">Ahorro estimado: ${escapeHtml(item.ahorro)}</p>` : ''}
              </article>
            `
          )
          .join('')}
      </div>
    `;
  };

  const renderPaginaCard = (page, index) => {
    const metric = page.performance ?? {};
    const score = typeof metric.score === 'number' ? metric.score : null;
    const detailId = `performance-details-${index}`;

    return `
      <article class="rounded-2xl border border-white/10 bg-gradient-to-br from-[#08121f]/90 via-[#050c18]/80 to-[#03070e]/90 shadow-lg shadow-black/40 px-4 py-4 space-y-4">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">URL</p>
            <a href="${escapeHtml(page.url)}" class="text-lg font-semibold text-slate-100 hover:text-white transition-colors block break-all" target="_blank" rel="noreferrer">${escapeHtml(page.url)}</a>
          </div>
          <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-semibold ${scoreColor(score)}">
            Score
            <span>${score !== null ? `${score}/100` : '—'}</span>
          </span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-slate-300 border-t border-b border-slate-800 py-3">
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">LCP</p>
            <p class="text-lg text-white mt-1">${escapeHtml(metric.lcp ?? '—')}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">FCP</p>
            <p class="text-lg text-white mt-1">${escapeHtml(metric.fcp ?? '—')}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">CLS</p>
            <p class="text-lg text-white mt-1">${escapeHtml(metric.cls ?? '—')}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">TBT/INP</p>
            <p class="text-lg text-white mt-1">${escapeHtml(metric.tbt ?? '—')}</p>
          </div>
        </div>
          <details class="bg-[#01060f]/80 border border-slate-800 rounded-2xl p-4" aria-labelledby="${detailId}">
          <summary id="${detailId}" class="cursor-pointer text-sm text-slate-200 font-semibold" aria-controls="${detailId}-opportunities" aria-expanded="false">
            Ver cuellos de botella
          </summary>
          <div id="${detailId}-opportunities">
            ${renderOpportunities(page.oportunidades ?? [])}
          </div>
        </details>
      </article>
    `;
  };

  const renderPaginasPerformance = (pages) => {
    if (!Array.isArray(pages) || pages.length === 0) {
      return '<div class="sonar-alert">No hay datos disponibles.</div>';
    }

    return `
      <div class="space-y-4">
        ${pages.map((page, index) => renderPaginaCard(page, index)).join('')}
      </div>
    `;
  };

  const renderResult = (payload) => {
    const resumen = renderResumenPerformance(payload);
    const lista = renderPaginasPerformance(payload.paginas ?? []);
    resultContainer.innerHTML = `${resumen}${lista}`;
  };

  const setLoading = (isLoading) => {
    if (refreshButton) {
      refreshButton.disabled = isLoading;
      refreshButton.textContent = isLoading ? 'Analizando…' : 'Actualizar análisis';
    }
    setState(isLoading ? 'Cargando rendimiento...' : '', false, isLoading);
  };

  const loadData = async () => {
    setLoading(true);
    try {
      const response = await fetch(endpoint, {
        method: 'GET',
        headers: {
          Accept: 'application/json',
        },
        credentials: 'same-origin',
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const payload = await response.json();
      if (payload.estado !== 'exito') {
        throw new Error(payload.mensaje ?? 'No se pudo obtener la información.');
      }
      renderResult(payload);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Error inesperado.';
      setState(message, true);
    } finally {
      setLoading(false);
    }
  };

  if (refreshButton) {
    refreshButton.addEventListener('click', () => {
      loadData();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadData();
  });
})();
