/* global window, document, fetch */
(function () {
    'use strict';

    const endpoints = {
        pages: '/api/heatmap/pages.php',
        summary: '/api/heatmap/summary.php',
    };

    const selectors = {
        canvas: document.querySelector('#heatmap-canvas'),
        pageSelect: document.querySelector('#heatmap-page'),
        refreshButton: document.querySelector('#heatmap-refresh'),
        totalClicks: document.querySelector('#heatmap-total-clicks'),
        selectedPage: document.querySelector('#heatmap-selected-page'),
        intensity: document.querySelector('#heatmap-intensity'),
        heatmapStatus: document.querySelector('#heatmap-status'),
        heatmapLegend: document.querySelector('#heatmap-legend'),
        heatmapEmpty: document.querySelector('#heatmap-empty'),
    };

    const chartNodes = {
        zoneBar: document.getElementById('heatmap-bar-zones'),
        distributionLine: document.getElementById('heatmap-line-distribution'),
    };
    const zoneLabels = ['Top', 'Middle', 'Bottom'];
    const zoneColors = ['#0ea5e9', '#facc15', '#fb923c'];
    const distributionColor = 'rgba(14, 165, 233, 0.95)';
    const distributionFill = 'rgba(14, 165, 233, 0.25)';
    let zoneBarChart = null;
    let distributionChart = null;

    if (!selectors.canvas || !selectors.pageSelect || !selectors.refreshButton) {
        return;
    }

    const canvasContext = selectors.canvas.getContext('2d');
    if (!canvasContext) {
        return;
    }

    let lastSummary = null;
    let resizeTimeout = null;

    window.addEventListener('DOMContentLoaded', init);

    function init() {
        adaptCanvas();
        window.addEventListener('resize', () => {
            if (resizeTimeout !== null) {
                clearTimeout(resizeTimeout);
            }
            resizeTimeout = setTimeout(() => {
                adaptCanvas();
                if (lastSummary) {
                    renderHeatmap(lastSummary);
                }
            }, 120);
        });

        selectors.pageSelect.addEventListener('change', () => {
            updateHeatmap();
        });

        selectors.refreshButton.addEventListener('click', () => {
            updateHeatmap();
        });

        loadPages();
    }

    async function loadPages() {
        toggleLoading(true, 'Cargando páginas…');
        try {
            const response = await fetch(endpoints.pages, {
                headers: {
                    Accept: 'application/json',
                },
            });
            const payload = await response.json();
            if (payload.status !== 'ok' || !Array.isArray(payload.pages)) {
                throw new Error('Respuesta inválida');
            }

            selectors.pageSelect.innerHTML = '';
            if (payload.pages.length === 0) {
                selectors.pageSelect.disabled = true;
                selectors.pageSelect.innerHTML = '<option value="">— sin datos —</option>';
                showStatus('Aún no hay registros de Heatmap.');
                return;
            }

            payload.pages.forEach((page) => {
                const option = document.createElement('option');
                option.value = page;
                option.textContent = page;
                selectors.pageSelect.appendChild(option);
            });

            selectors.pageSelect.disabled = false;
            selectors.pageSelect.selectedIndex = 0;
            await updateHeatmap();
        } catch (error) {
            console.error(error);
            showStatus('Error cargando páginas.', true);
        } finally {
            toggleLoading(false);
        }
    }

    async function updateHeatmap() {
        const selectedPage = selectors.pageSelect.value;
        if (!selectedPage) {
            showStatus('Selecciona una página');
            return;
        }

        toggleLoading(true, 'Actualizando mapa…');
        try {
            const response = await fetch(`${endpoints.summary}?page=${encodeURIComponent(selectedPage)}`, {
                headers: {
                    Accept: 'application/json',
                },
            });
            const payload = await response.json();
            if (payload.status !== 'ok' || !Array.isArray(payload.grid)) {
                throw new Error('Datos inválidos de heatmap');
            }

            lastSummary = payload;
            renderHeatmap(payload);
            selectors.selectedPage.textContent = selectedPage;
            selectors.totalClicks.textContent = payload.totalClicks.toLocaleString();
            showStatus('Mapa cargado');
        } catch (error) {
            console.error(error);
            showStatus('No fue posible actualizar el heatmap.', true);
        } finally {
            toggleLoading(false);
        }
    }

    function renderHeatmap(summary) {
        const rows = Number(summary.rows) || 20;
        const cols = Number(summary.cols) || 20;

        const aggregated = aggregateGrid(summary.grid, rows, cols);
        updateZoneChart(aggregated.zoneCounts);
        updateDistributionChart(aggregated.rowSums, rows);

        selectors.heatmapEmpty?.classList.add('hidden');
        canvasContext.clearRect(0, 0, selectors.canvas.width, selectors.canvas.height);
        canvasContext.save();
        canvasContext.globalCompositeOperation = 'lighter';

        const flatGrid = summary.grid.flat();
        const highestValue = flatGrid.length > 0 ? Math.max(...flatGrid) : 0;
        const maxValue = Math.max(highestValue, 1);
        if (selectors.intensity) {
            selectors.intensity.textContent = highestValue.toLocaleString();
        }

        const { width, height } = selectors.canvas.getBoundingClientRect();
        const cellWidth = width / cols;
        const cellHeight = height / rows;
        const radiusBase = Math.min(cellWidth, cellHeight) * 0.8;

        for (let row = 0; row < rows; row++) {
            for (let col = 0; col < cols; col++) {
                const value = Number(summary.grid[row]?.[col] ?? 0);
                if (value <= 0) {
                    continue;
                }

                const intensity = Math.min(1, value / maxValue);
                const centerX = col * cellWidth + cellWidth / 2;
                const centerY = row * cellHeight + cellHeight / 2;
                const radius = radiusBase * (0.6 + 0.6 * intensity);
                drawGlowPoint(canvasContext, centerX, centerY, radius, intensity);
            }
        }

        canvasContext.restore();

        if (!summary.totalClicks) {
            selectors.heatmapEmpty?.classList.remove('hidden');
        }

        if (selectors.heatmapLegend) {
            selectors.heatmapLegend.style.opacity = summary.totalClicks ? '1' : '0.4';
        }
    }

    function drawGlowPoint(ctx, x, y, radius, intensity) {
        const palette = getColorPalette(intensity);
        const gradient = ctx.createRadialGradient(x, y, radius * 0.1, x, y, radius);
        gradient.addColorStop(0, palette.inner);
        gradient.addColorStop(0.5, palette.mid);
        gradient.addColorStop(1, palette.outer);

        ctx.beginPath();
        ctx.fillStyle = gradient;
        ctx.globalAlpha = 0.8;
        ctx.arc(x, y, radius, 0, Math.PI * 2);
        ctx.fill();
        ctx.closePath();
    }

    function getColorPalette(intensity) {
        if (intensity < 0.4) {
            return {
                inner: 'rgba(34, 211, 238, 0.9)',
                mid: 'rgba(14, 165, 233, 0.35)',
                outer: 'rgba(15, 23, 42, 0)',
            };
        }
        if (intensity < 0.75) {
            return {
                inner: 'rgba(251, 191, 36, 0.95)',
                mid: 'rgba(251, 146, 60, 0.4)',
                outer: 'rgba(16, 24, 40, 0)',
            };
        }

        return {
            inner: 'rgba(239, 68, 68, 0.95)',
            mid: 'rgba(244, 63, 94, 0.5)',
            outer: 'rgba(15, 23, 42, 0)',
        };
    }

    function isChartReady() {
        return typeof window.Chart === 'function' || typeof window.Chart === 'object';
    }

    function aggregateGrid(grid, rows, cols) {
        const zoneCounts = [0, 0, 0];
        const rowSums = new Array(Math.max(rows, 0)).fill(0);
        const zoneSize = Math.max(1, Math.ceil(rows / 3));

        for (let row = 0; row < rows; row++) {
            const cells = Array.isArray(grid[row]) ? grid[row] : [];
            let rowTotal = 0;
            for (let col = 0; col < cols; col++) {
                const value = Number(cells[col] ?? 0);
                if (value > 0) {
                    rowTotal += value;
                }
            }
            rowSums[row] = rowTotal;
            const zoneIndex = Math.min(2, Math.floor(row / zoneSize));
            zoneCounts[zoneIndex] += rowTotal;
        }

        return { rowSums, zoneCounts };
    }

    function updateZoneChart(zoneCounts) {
        if (!chartNodes.zoneBar || !isChartReady()) {
            return;
        }

        const data = zoneCounts.map((value) => Number(value ?? 0));

        if (!zoneBarChart) {
            zoneBarChart = new window.Chart(chartNodes.zoneBar, {
                type: 'bar',
                data: {
                    labels: zoneLabels,
                    datasets: [
                        {
                            label: 'Clicks por zona',
                            data,
                            backgroundColor: zoneColors,
                            borderRadius: 12,
                            barThickness: 24,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        x: {
                            ticks: { color: '#cbd5f5' },
                            grid: { display: false },
                        },
                        y: {
                            ticks: { color: '#cbd5f5', beginAtZero: true },
                            grid: { color: 'rgba(255, 255, 255, 0.08)' },
                        },
                    },
                },
            });
        } else {
            zoneBarChart.data.datasets[0].data = data;
            zoneBarChart.update();
        }
    }

    function updateDistributionChart(rowSums, rows) {
        if (!chartNodes.distributionLine || !isChartReady()) {
            return;
        }

        const normalizedRows = Math.max(rows, 1);
        const labelDenominator = Math.max(normalizedRows - 1, 1);
        const labels = rowSums.map((_, index) => `${Math.round((index / labelDenominator) * 100)}%`);
        const data = rowSums.map((value) => Number(value ?? 0));

        if (!distributionChart) {
            distributionChart = new window.Chart(chartNodes.distributionLine, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Clicks por altura',
                            data,
                            borderColor: distributionColor,
                            backgroundColor: distributionFill,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 2,
                            pointHoverRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        x: {
                            ticks: { color: '#cbd5f5' },
                            grid: { color: 'rgba(255, 255, 255, 0.06)' },
                        },
                        y: {
                            ticks: { color: '#cbd5f5', beginAtZero: true },
                            grid: { color: 'rgba(255, 255, 255, 0.06)' },
                        },
                    },
                },
            });
        } else {
            distributionChart.data.labels = labels;
            distributionChart.data.datasets[0].data = data;
            distributionChart.update();
        }
    }

    function adaptCanvas() {
        const ratio = window.devicePixelRatio || 1;
        const { width, height } = selectors.canvas.getBoundingClientRect();
        selectors.canvas.width = width * ratio;
        selectors.canvas.height = height * ratio;
        canvasContext.setTransform(ratio, 0, 0, ratio, 0, 0);
        canvasContext.clearRect(0, 0, selectors.canvas.width, selectors.canvas.height);
    }

    function toggleLoading(isLoading, message = '') {
        selectors.refreshButton.disabled = isLoading;
        if (message) {
            showStatus(message);
        }
    }

    function showStatus(text, isError = false) {
        if (!selectors.heatmapStatus) {
            return;
        }
        selectors.heatmapStatus.textContent = text;
        selectors.heatmapStatus.classList.toggle('text-red-400', isError);
        selectors.heatmapStatus.classList.toggle('text-slate-300', !isError);
    }
})();
