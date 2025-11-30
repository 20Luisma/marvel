// Security Dashboard - Clean Marvel Album
// Carga métricas de seguridad desde SecurityHeaders.com y Mozilla Observatory

document.addEventListener('DOMContentLoaded', () => {
    const securityHeadersCard = document.getElementById('security-headers-card');
    const mozillaCard = document.getElementById('mozilla-card');
    const rescanBtn = document.getElementById('rescan-btn');
    const lastScanEl = document.getElementById('last-scan');

    // Cargar datos iniciales
    loadSecurityMetrics();

    // Botón re-escanear
    if (rescanBtn) {
        rescanBtn.addEventListener('click', () => {
            rescanBtn.disabled = true;
            rescanBtn.textContent = 'Actualizando...';
            loadSecurityMetrics(true);
        });
    }

    async function loadSecurityMetrics(force = false) {
        try {
            showSkeletons();

            const url = force ? '/api/security-metrics.php?force=1' : '/api/security-metrics.php';
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error('Error al cargar métricas');
            }

            const data = await response.json();
            
            // Actualizar SecurityHeaders.com
            updateSecurityHeadersCard(data.securityHeaders);
            
            // Actualizar Mozilla Observatory
            updateMozillaCard(data.mozillaObservatory);
            
            // Actualizar timestamp
            updateLastScan(data.lastScan, data.fromCache);

        } catch (error) {
            console.error('Error:', error);
            showError();
        } finally {
            if (rescanBtn) {
                rescanBtn.disabled = false;
                rescanBtn.textContent = 'Actualizar';
            }
        }
    }

    function updateSecurityHeadersCard(data) {
        if (!securityHeadersCard) {
            return;
        }

        // Si hay error, mostrar mensaje de error
        if (data.error) {
            securityHeadersCard.innerHTML = `
                <div class="security-card__header">
                    <h3>SecurityHeaders.com</h3>
                    <span class="grade grade-na">N/A</span>
                </div>
                <div class="security-card__body">
                    <div class="error-message">
                        <p>⚠️ ${data.error}</p>
                        <p class="text-sm text-gray-400 mt-2">La API externa no está disponible. Intenta re-escanear más tarde.</p>
                    </div>
                </div>
            `;
            return;
        }

        const gradeClass = getGradeClass(data.grade);
        const headersCount = Object.keys(data.headers || {}).length;
        const missingCount = (data.missing || []).length;

        securityHeadersCard.innerHTML = `
            <div class="security-card__header">
                <h3>SecurityHeaders.com</h3>
                <span class="grade ${gradeClass}">${data.grade}</span>
            </div>
            <div class="security-card__body">
                <div class="metric">
                    <span class="label">Headers Presentes:</span>
                    <span class="value">${headersCount}</span>
                </div>
                <div class="metric">
                    <span class="label">Headers Faltantes:</span>
                    <span class="value">${missingCount}</span>
                </div>
                ${missingCount > 0 ? `
                <div class="metric-list">
                    <span class="label">Faltantes:</span>
                    <ul>
                        ${(data.missing || []).slice(0, 3).map(h => `<li>${h}</li>`).join('')}
                    </ul>
                </div>
                ` : ''}
            </div>
        `;
    }

    function updateMozillaCard(data) {
        if (!mozillaCard) {
            return;
        }

        // Si hay error, mostrar mensaje de error
        if (data.error) {
            mozillaCard.innerHTML = `
                <div class="security-card__header">
                    <h3>Mozilla Observatory</h3>
                    <span class="grade grade-na">N/A</span>
                </div>
                <div class="security-card__body">
                    <div class="error-message">
                        <p>⚠️ ${data.error}</p>
                        <p class="text-sm text-gray-400 mt-2">La API externa no está disponible. Intenta re-escanear más tarde.</p>
                    </div>
                </div>
            `;
            return;
        }

        const gradeClass = getGradeClass(data.grade);

        mozillaCard.innerHTML = `
            <div class="security-card__header">
                <h3>Mozilla Observatory</h3>
                <span class="grade ${gradeClass}">${data.grade}</span>
            </div>
            <div class="security-card__body">
                <div class="metric">
                    <span class="label">Puntuación:</span>
                    <span class="value">${data.score}/100</span>
                </div>
                <div class="metric">
                    <span class="label">Tests Pasados:</span>
                    <span class="value text-green-400">${data.tests_passed || 0}</span>
                </div>
                <div class="metric">
                    <span class="label">Tests Fallados:</span>
                    <span class="value text-red-400">${data.tests_failed || 0}</span>
                </div>
            </div>
        `;
    }

    function updateLastScan(timestamp, fromCache) {
        if (!lastScanEl) return;

        const date = new Date(timestamp);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000 / 60); // minutos

        let timeAgo;
        if (diff < 1) {
            timeAgo = 'hace menos de 1 minuto';
        } else if (diff < 60) {
            timeAgo = `hace ${diff} minuto${diff > 1 ? 's' : ''}`;
        } else {
            const hours = Math.floor(diff / 60);
            timeAgo = `hace ${hours} hora${hours > 1 ? 's' : ''}`;
        }

        lastScanEl.textContent = `Último escaneo: ${timeAgo}${fromCache ? ' (cache)' : ''}`;
    }

    function getGradeClass(grade) {
        if (!grade || grade === 'N/A') return 'grade-na';
        
        const g = grade.toUpperCase();
        if (g === 'A' || g === 'A+') return 'grade-a';
        if (g === 'B' || g === 'C') return 'grade-b';
        return 'grade-f';
    }

    function showSkeletons() {
        const skeleton = `
            <div class="skeleton-loader">
                <div class="skeleton-line"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line short"></div>
            </div>
        `;
        
        if (securityHeadersCard) securityHeadersCard.innerHTML = skeleton;
        if (mozillaCard) mozillaCard.innerHTML = skeleton;
    }

    function showError() {
        const errorHtml = `
            <div class="error-message">
                <p>⚠️ Error al cargar datos</p>
            </div>
        `;
        
        if (securityHeadersCard) securityHeadersCard.innerHTML = errorHtml;
        if (mozillaCard) mozillaCard.innerHTML = errorHtml;
    }
});
