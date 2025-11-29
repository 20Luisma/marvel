"use strict";

(function () {
  const endpoint = '/api/snyk-scan.php';
  const refreshButton = document.getElementById('snyk-refresh-button');
  const loader = document.getElementById('snyk-loader');
  const errorContainer = document.getElementById('snyk-error');
  const metricsContainer = document.getElementById('snyk-metrics');

  // Metric elements
  const metricTotal = document.getElementById('metric-total');
  const metricHigh = document.getElementById('metric-high');
  const metricMedium = document.getElementById('metric-medium');
  const metricLow = document.getElementById('metric-low');
  const metricProject = document.getElementById('metric-project');
  const lastScanElement = document.getElementById('snyk-last-scan');

  if (!refreshButton || !loader || !errorContainer || !metricsContainer) {
    console.error('Snyk: Missing required DOM elements');
    return;
  }

  const showLoader = () => {
    if (loader) loader.classList.remove('hidden');
    if (errorContainer) errorContainer.style.display = 'none';
    if (refreshButton) {
      refreshButton.disabled = true;
      refreshButton.textContent = 'Escaneando...';
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
      errorContainer.style.display = 'block';
    }
  };

  const hideError = () => {
    if (errorContainer) {
      errorContainer.style.display = 'none';
    }
  };

  const updateMetrics = (data) => {
    if (metricTotal) metricTotal.textContent = data.total ?? '—';
    if (metricHigh) metricHigh.textContent = data.high ?? '—';
    if (metricMedium) metricMedium.textContent = data.medium ?? '—';
    if (metricLow) metricLow.textContent = data.low ?? '—';
    if (metricProject) {
      const projectName = data.project ?? '—';
      metricProject.textContent = projectName.length > 30 
        ? projectName.substring(0, 30) + '...' 
        : projectName;
      metricProject.title = projectName;
    }
    if (lastScanElement) {
      lastScanElement.textContent = data.last_scan ?? '—';
    }
  };

  const loadData = async () => {
    showLoader();
    hideError();

    try {
      const response = await fetch(endpoint, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();

      if (!data.ok) {
        throw new Error(data.error || 'Error desconocido al escanear con Snyk');
      }

      updateMetrics(data);
      console.log('✅ Snyk scan completed:', data);

    } catch (error) {
      console.error('❌ Snyk scan error:', error);
      showError(error.message || 'Error al conectar con Snyk API');
    } finally {
      hideLoader();
    }
  };

  // Event listeners
  if (refreshButton) {
    refreshButton.addEventListener('click', () => {
      loadData();
    });
  }

  // Load data on page load
  document.addEventListener('DOMContentLoaded', () => {
    console.log('🔍 Snyk Code Audit initialized');
    loadData();
  });
})();
