"use strict";

(function () {
  const endpoint = '/api/ai-token-metrics.php';
  const refreshButton = document.getElementById('metrics-refresh-button');
  const loader = document.getElementById('metrics-loader');
  const errorContainer = document.getElementById('metrics-error');

  if (!refreshButton || !loader || !errorContainer) {
    console.error('AI Metrics: Missing required DOM elements');
    return;
  }

  const showLoader = () => {
    if (loader) loader.classList.remove('hidden');
    if (errorContainer) errorContainer.classList.add('hidden');
    if (refreshButton) {
      refreshButton.disabled = true;
      refreshButton.textContent = 'Cargando...';
    }
  };

  const hideLoader = () => {
    if (loader) loader.classList.add('hidden');
    if (refreshButton) {
      refreshButton.disabled = false;
      refreshButton.textContent = 'Actualizar';
    }
  };

  const showError = (message) => {
    if (errorContainer) {
      errorContainer.textContent = message;
      errorContainer.classList.remove('hidden');
    }
  };

  const updateGlobalMetrics = (global, byFeature) => {
    const totalCallsFromFeatures = (byFeature || []).reduce(
      (acc, feature) => acc + (feature.calls || 0),
      0
    );

    const totalCalls = global.total_calls || totalCallsFromFeatures || 0;

    console.debug('[AI-Metrics] totalCalls', {
      fromGlobal: global.total_calls,
      fromFeatures: totalCallsFromFeatures,
      final: totalCalls,
    });

    const elTotalCalls = document.getElementById('metric-total-calls');
    if (elTotalCalls) {
      elTotalCalls.textContent = totalCalls;
    }

    const elTotalTokens = document.getElementById('metric-total-tokens');
    if (elTotalTokens) {
      elTotalTokens.textContent = (global.total_tokens || 0).toLocaleString();
    }

    const elTokensToday = document.getElementById('metric-tokens-today');
    if (elTokensToday) {
      elTokensToday.textContent = (global.tokens_today || 0).toLocaleString();
    }

    const elTokens7Days = document.getElementById('metric-tokens-7days');
    if (elTokens7Days) {
      elTokens7Days.textContent = (global.tokens_last_7_days || 0).toLocaleString();
    }

    const elAvgTokens = document.getElementById('metric-avg-tokens');
    if (elAvgTokens) {
      elAvgTokens.textContent = global.avg_tokens_per_call || 0;
    }

    const elAvgLatency = document.getElementById('metric-avg-latency');
    if (elAvgLatency) {
      elAvgLatency.textContent = (global.avg_latency_ms || 0) + ' ms';
    }

    const elFailedCalls = document.getElementById('metric-failed-calls');
    if (elFailedCalls) {
      elFailedCalls.textContent = global.failed_calls || 0;
    }

    // Show cost cards if available
    if (global.estimated_cost_total !== undefined) {
      const costCard = document.getElementById('cost-card');
      const elCostTotal = document.getElementById('metric-cost-total');
      if (costCard) costCard.style.display = 'block';
      if (elCostTotal) elCostTotal.textContent = `$${global.estimated_cost_total}`;
    }
    if (global.estimated_cost_total_eur !== undefined) {
      const costEurCard = document.getElementById('cost-eur-card');
      const elCostTotalEur = document.getElementById('metric-cost-total-eur');
      if (costEurCard) costEurCard.style.display = 'block';
      if (elCostTotalEur) elCostTotalEur.textContent = `€${global.estimated_cost_total_eur}`;
    }
  };

  const updateByModel = (byModel) => {
    const container = document.getElementById('by-model-container');
    if (!container) return;

    if (!byModel || byModel.length === 0) {
      container.innerHTML = '<p class="text-slate-400">No hay datos disponibles</p>';
      return;
    }

    container.innerHTML = byModel.map(item => `
      <div class="metrics-model-card">
        <div>
          <p class="text-xs text-slate-400 uppercase">Modelo</p>
          <p class="text-white font-semibold">${item.model}</p>
        </div>
        <div>
          <p class="text-xs text-slate-400 uppercase">Llamadas</p>
          <p class="text-cyan-300 font-semibold">${item.calls}</p>
        </div>
        <div>
          <p class="text-xs text-slate-400 uppercase">Total Tokens</p>
          <p class="text-blue-300 font-semibold">${item.total_tokens.toLocaleString()}</p>
        </div>
        <div>
          <p class="text-xs text-slate-400 uppercase">Avg Tokens</p>
          <p class="text-green-300 font-semibold">${item.avg_tokens}</p>
        </div>
        <div>
          <p class="text-xs text-slate-400 uppercase">Avg Latency</p>
          <p class="text-purple-300 font-semibold">${item.avg_latency_ms} ms</p>
        </div>
      </div>
    `).join('');
  };

  const updateByFeature = (byFeature) => {
    const container = document.getElementById('by-feature-container');
    if (!container) return;

    if (!byFeature || byFeature.length === 0) {
      container.innerHTML = '<p class="text-slate-400">No hay datos disponibles</p>';
      return;
    }

    container.innerHTML = byFeature.map(item => `
      <div class="metrics-feature-card">
        <div>
          <p class="text-xs text-slate-400 uppercase">Feature</p>
          <p class="text-white font-semibold">${item.feature}</p>
        </div>
        <div>
          <p class="text-xs text-slate-400 uppercase">Llamadas</p>
          <p class="text-cyan-300 font-semibold">${item.calls}</p>
        </div>
        <div>
          <p class="text-xs text-slate-400 uppercase">Total Tokens</p>
          <p class="text-blue-300 font-semibold">${item.total_tokens.toLocaleString()}</p>
        </div>
        <div>
          <p class="text-xs text-slate-400 uppercase">Avg Tokens</p>
          <p class="text-green-300 font-semibold">${item.avg_tokens}</p>
        </div>
      </div>
    `).join('');
  };

  const updateRecentCalls = (recentCalls) => {
    const tbody = document.getElementById('recent-calls-body');
    if (!tbody) return;

    if (!recentCalls || recentCalls.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-3 text-center text-slate-400">No hay llamadas recientes</td></tr>';
      return;
    }

    tbody.innerHTML = recentCalls.map(call => {
      const date = new Date(call.ts);
      const dateStr = date.toLocaleString('es-ES');
      const statusClass = call.success ? 'status-badge--success' : 'status-badge--error';
      const statusText = call.success ? 'OK' : 'Error';

      return `
        <tr>
          <td class="px-4 py-3 text-slate-300">${dateStr}</td>
          <td class="px-4 py-3 text-slate-300">${call.feature}</td>
          <td class="px-4 py-3 text-slate-300">${call.model}</td>
          <td class="px-4 py-3 text-blue-300 font-semibold">${call.total_tokens}</td>
          <td class="px-4 py-3 text-purple-300">${call.latency_ms} ms</td>
          <td class="px-4 py-3">
            <span class="status-badge ${statusClass}">${statusText}</span>
          </td>
        </tr>
      `;
    }).join('');
  };

  const loadData = async () => {
    console.log('[AI-Metrics] Reload metrics at', new Date().toISOString());
    showLoader();

    try {
      const response = await fetch(`${endpoint}?t=${Date.now()}`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache',
        },
        credentials: 'same-origin',
        cache: 'no-store',
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();

      if (!data.ok) {
        throw new Error(data.error || 'Error desconocido al cargar métricas');
      }

      updateGlobalMetrics(data.global || {}, data.by_feature || []);
      updateByModel(data.by_model || []);
      updateByFeature(data.by_feature || []);
      updateRecentCalls(data.recent_calls || []);

      console.log('✅ AI Metrics loaded:', data);

    } catch (error) {
      console.error('❌ AI Metrics error:', error);
      showError(error.message || 'Error al conectar con la API de métricas');
    } finally {
      hideLoader();
    }
  };

  // Event listeners
  if (refreshButton) {
    refreshButton.addEventListener('click', () => {
      console.log('[AI-Metrics] Manual refresh click');
      loadData();
    });
  }

  // Load data on page load
  document.addEventListener('DOMContentLoaded', () => {
    console.log('🤖 AI Token Metrics initialized');
    loadData();
  });
})();
