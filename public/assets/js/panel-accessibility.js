"use strict";

(function () {
  const endpoint = '/api/accessibility-marvel.php';
  const runButton = document.getElementById('btn-accessibility-run');
  const resultContainer = document.getElementById('accessibility-result');

  if (!runButton || !resultContainer) {
    return;
  }

  const originalLabel = runButton.textContent.trim();
  runButton.dataset.originalText = originalLabel;

  const escapeHtml = (value) => {
    const str = String(value ?? '');
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  const formatNumber = (value) => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
      return '—';
    }
    return new Intl.NumberFormat('es-ES').format(Number(value));
  };

  const formatAimScore = (value) =>
    value === null || value === undefined || Number.isNaN(Number(value))
      ? '—'
      : Number(value).toFixed(1);

  const renderError = (message) => `
    <div class="sonar-alert sonar-alert--error" role="alert" aria-live="assertive" aria-atomic="true">
      <strong>Error:</strong> ${escapeHtml(message ?? 'La respuesta no pudo ser procesada.')}
    </div>
  `;

  // 🔹 NUEVO RESUMEN CON 4 CARDS
  const renderResumenAccesibilidad = (payload) => {
    const resumen = payload.resumen_global ?? {};

    return `
      <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <!-- Páginas analizadas -->
          <article class="rounded-2xl border border-slate-700/70 bg-slate-900/70 px-4 py-3 shadow shadow-black/40">
            <h3 class="text-[0.7rem] font-semibold tracking-[0.2em] text-slate-400 uppercase">
              Páginas analizadas
            </h3>
            <p class="mt-1 text-3xl font-bold text-sky-300">
              ${formatNumber(payload.total_paginas ?? 0)}
            </p>
            <p class="mt-1 text-[0.7rem] text-slate-400">
              URLs procesadas por WAVE
            </p>
          </article>

          <!-- Errores -->
          <article class="rounded-2xl border border-red-700/60 bg-[#1a0b10] px-4 py-3 shadow shadow-black/40">
            <h3 class="text-[0.7rem] font-semibold tracking-[0.2em] text-red-300 uppercase">
              Errores detectados
            </h3>
            <p class="mt-1 text-3xl font-bold text-red-400">
              ${formatNumber(resumen.total_errores ?? 0)}
            </p>
            <p class="mt-1 text-[0.7rem] text-red-200/80">
              Violaciones críticas
            </p>
          </article>

          <!-- Contraste -->
          <article class="rounded-2xl border border-amber-500/50 bg-[#1a1305] px-4 py-3 shadow shadow-black/40">
            <h3 class="text-[0.7rem] font-semibold tracking-[0.2em] text-amber-200 uppercase">
              Problemas de contraste
            </h3>
            <p class="mt-1 text-3xl font-bold text-amber-300">
              ${formatNumber(resumen.total_contraste ?? 0)}
            </p>
            <p class="mt-1 text-[0.7rem] text-amber-200/80">
              Color y legibilidad
            </p>
          </article>

          <!-- Alertas -->
          <article class="rounded-2xl border border-emerald-500/50 bg-[#02140f] px-4 py-3 shadow shadow-black/40">
            <h3 class="text-[0.7rem] font-semibold tracking-[0.2em] text-emerald-200 uppercase">
              Alertas y avisos
            </h3>
            <p class="mt-1 text-3xl font-bold text-emerald-300">
              ${formatNumber(resumen.total_alertas ?? 0)}
            </p>
            <p class="mt-1 text-[0.7rem] text-emerald-200/80">
              Señales sin gravedad crítica
            </p>
          </article>
        </div>
      </div>
    `;
  };

  const renderTablaPaginas = (pages) => {
    if (!Array.isArray(pages) || pages.length === 0) {
      return `<div class="sonar-alert" role="status">No se analizaron páginas todavía.</div>`;
    }

    const rows = pages
      .map((page) => {
        const status = page.estado === 'error' ? 'error' : 'exito';
        const badge =
          status === 'error'
            ? '<span class="text-amber-300 font-semibold text-xs uppercase">Error</span>'
            : '<span class="text-emerald-300 font-semibold text-xs uppercase">Éxito</span>';

        const link = page.waveUrl
          ? `<a href="${escapeHtml(page.waveUrl)}" target="_blank" rel="noopener" class="text-emerald-300 hover:text-white text-sm">Ver informe WAVE</a>`
          : '—';

        const titleContent =
          status === 'error'
            ? `<span class="flex flex-col gap-1"><strong>${escapeHtml(
                page.url
              )}</strong><span class="text-amber-200 text-xs">${escapeHtml(
                page.mensaje ?? 'No se pudo obtener el informe.'
              )}</span></span>`
            : escapeHtml(page.titulo ?? page.url);

        return `
          <tr class="border-b border-slate-800">
            <td class="px-3 py-2 text-sm text-left">
              <a href="${escapeHtml(
                page.url
              )}" target="_blank" rel="noopener" class="text-slate-200 hover:text-white underline">${escapeHtml(
          page.url
        )}</a>
            </td>
            <td class="px-3 py-2 text-sm text-left text-slate-300">${titleContent}</td>
            <td class="px-3 py-2 text-sm text-center text-slate-200">${formatNumber(
              page.errores
            )}</td>
            <td class="px-3 py-2 text-sm text-center text-slate-200">${formatNumber(
              page.contraste
            )}</td>
            <td class="px-3 py-2 text-sm text-center text-slate-200">${formatNumber(
              page.alertas
            )}</td>
            <td class="px-3 py-2 text-sm text-center text-slate-200">${formatAimScore(
              page.aimScore
            )}</td>
            <td class="px-3 py-2 text-sm text-center text-emerald-200">${link}</td>
            <td class="px-3 py-2 text-sm text-center">${badge}</td>
          </tr>
        `;
      })
      .join('');

    return `
      <div class="rounded-2xl border border-slate-700/80 bg-slate-900/50 p-4 space-y-3 overflow-x-auto">
        <div class="flex items-center justify-between gap-3">
          <p class="text-sm uppercase tracking-[0.3em] text-slate-400">Resultados por página</p>
          <span class="text-xs text-slate-500">Orden natural</span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-slate-300 text-sm">
            <thead>
              <tr class="text-xs text-slate-400 uppercase tracking-[0.2em]">
                <th class="px-3 py-2">URL</th>
                <th class="px-3 py-2">Título</th>
                <th class="px-3 py-2 text-center">Errores</th>
                <th class="px-3 py-2 text-center">Contraste</th>
                <th class="px-3 py-2 text-center">Alertas</th>
                <th class="px-3 py-2 text-center">AIM score</th>
                <th class="px-3 py-2 text-center">Informe</th>
                <th class="px-3 py-2 text-center">Estado</th>
              </tr>
            </thead>
            <tbody>
              ${rows}
            </tbody>
          </table>
        </div>
      </div>
    `;
  };

  const parseResponse = async (response) => {
    const text = await response.text();
    if (!text) {
      return { responseText: '', payload: null };
    }

    try {
      const payload = JSON.parse(text);
      return { responseText: text, payload };
    } catch {
      return { responseText: text, payload: null };
    }
  };

  const startLoading = () => {
    runButton.disabled = true;
    runButton.textContent = 'Analizando…';
  };

  const stopLoading = () => {
    runButton.disabled = false;
    runButton.textContent = runButton.dataset.originalText ?? originalLabel;
  };

  runButton.addEventListener('click', async () => {
    startLoading();
    resultContainer.innerHTML =
      '<div class="sonar-alert" role="status">Consultando WAVE…</div>';

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({}),
      });

      const { payload } = await parseResponse(response);
      if (!payload) {
        throw new Error('Respuesta vacía o inválida de la API.');
      }

      if (!response.ok) {
        throw new Error(payload.mensaje ?? `HTTP ${response.status}`);
      }

      if (payload.estado !== 'exito') {
        throw new Error(payload.mensaje ?? 'El análisis no pudo completarse.');
      }

      const resumenHtml = renderResumenAccesibilidad(payload);
      const tablaHtml = renderTablaPaginas(payload.paginas ?? []);
      resultContainer.innerHTML = resumenHtml + tablaHtml;
    } catch (error) {
      const message =
        error instanceof Error ? error.message : 'Error inesperado.';
      resultContainer.innerHTML = renderError(message);
    } finally {
      stopLoading();
    }
  });
})();
