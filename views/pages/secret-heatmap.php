<?php
declare(strict_types=1);

use App\Infrastructure\Http\AuthGuards;

AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$pageTitle = 'Clean Marvel Album — Heatmap';
$additionalStyles = ['/assets/css/seccion.css', '/assets/css/heatmap.css'];
$activeTopAction = 'secret';
$scripts = ['/assets/js/heatmap-viewer.js'];

require_once __DIR__ . '/../layouts/header.php';
$cspNonce = $_SERVER['CSP_NONCE'] ?? null;
?>

<header class="app-hero app-hero--tech heatmap-hero">
  <div class="app-hero__inner">
    <div class="space-y-3 max-w-3xl">
      <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
      <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
        Mide cada clic que ocurre dentro de Clean Marvel Album sin salir del panel.
      </p>
      <p class="app-hero__meta text-base text-slate-300">
        Un mapa de calor en tiempo real para analizar la atención del usuario y encontrar hotspots.
      </p>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="site-main seccion-main">
  <div class="max-w-6xl mx-auto py-10 px-4 space-y-8">
    <section class="heatmap-panel section-lined">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 mb-8">
        <div class="space-y-2">
          <h2 class="sonar-hero-title text-4xl text-white">Marvel Heatmap Analytics</h2>
          <p class="text-slate-300 text-sm">Mapa de calor de interacciones de usuarios</p>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- PANEL MULTI-CLOUD: Control de nodos en tiempo real             -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div id="multicloud-panel" style="
        background: linear-gradient(135deg, rgba(15,23,42,0.95) 0%, rgba(30,41,59,0.95) 100%);
        border: 1px solid rgba(59,130,246,0.3);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 28px;
        position: relative;
        overflow: hidden;
      ">
        <!-- Fondo decorativo -->
        <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(59,130,246,0.08) 0%,transparent 70%);pointer-events:none;"></div>

        <!-- Header del panel -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:1.3rem;">🌐</span>
            <div>
              <p style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.3em;color:#64748b;margin:0;">Infraestructura Multi-Cloud</p>
              <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin:2px 0 0;">Control de Nodos — Demo Write-to-Both</h3>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:8px 14px;">
            <span style="font-size:0.7rem;color:#94a3b8;">Cola pendiente:</span>
            <span id="mc-queue-count" style="font-size:1rem;font-weight:700;color:#f59e0b;">—</span>
            <span style="font-size:0.7rem;color:#94a3b8;">clicks</span>
          </div>
        </div>

        <!-- Nodos -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

          <!-- Nodo GCP -->
          <div id="mc-node-gcp" style="
            background:rgba(15,23,42,0.7);
            border:2px solid rgba(59,130,246,0.4);
            border-radius:12px;
            padding:18px;
            transition:all 0.3s ease;
          ">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
              <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:1.1rem;">🔵</span>
                <div>
                  <p style="font-size:0.8rem;font-weight:700;color:#f1f5f9;margin:0;">Google Cloud</p>
                  <p style="font-size:0.65rem;color:#64748b;margin:0;">us-east1 · South Carolina</p>
                </div>
              </div>
              <div id="mc-badge-gcp" style="
                display:flex;align-items:center;gap:5px;
                background:rgba(16,185,129,0.15);
                border:1px solid rgba(16,185,129,0.4);
                border-radius:20px;padding:4px 10px;
              ">
                <span id="mc-dot-gcp" style="width:7px;height:7px;border-radius:50%;background:#10b981;display:inline-block;animation:mc-pulse 2s infinite;"></span>
                <span id="mc-label-gcp" style="font-size:0.65rem;font-weight:600;color:#10b981;text-transform:uppercase;letter-spacing:0.1em;">ONLINE</span>
              </div>
            </div>
            <p style="font-size:0.7rem;color:#475569;margin:0 0 14px;font-family:monospace;">34.74.102.123:8080</p>
            <button id="mc-btn-gcp" style="
              width:100%;padding:9px;border-radius:8px;border:none;cursor:pointer;
              font-size:0.75rem;font-weight:600;letter-spacing:0.05em;
              background:rgba(239,68,68,0.15);color:#f87171;
              border:1px solid rgba(239,68,68,0.3);
              transition:all 0.2s ease;
            ">
              ⏸ SIMULAR CAÍDA
            </button>
          </div>

          <!-- Nodo AWS -->
          <div id="mc-node-aws" style="
            background:rgba(15,23,42,0.7);
            border:2px solid rgba(245,158,11,0.4);
            border-radius:12px;
            padding:18px;
            transition:all 0.3s ease;
          ">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
              <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:1.1rem;">🟠</span>
                <div>
                  <p style="font-size:0.8rem;font-weight:700;color:#f1f5f9;margin:0;">Amazon Web Services</p>
                  <p style="font-size:0.65rem;color:#64748b;margin:0;">eu-west-3 · París</p>
                </div>
              </div>
              <div id="mc-badge-aws" style="
                display:flex;align-items:center;gap:5px;
                background:rgba(16,185,129,0.15);
                border:1px solid rgba(16,185,129,0.4);
                border-radius:20px;padding:4px 10px;
              ">
                <span id="mc-dot-aws" style="width:7px;height:7px;border-radius:50%;background:#10b981;display:inline-block;animation:mc-pulse 2s infinite;"></span>
                <span id="mc-label-aws" style="font-size:0.65rem;font-weight:600;color:#10b981;text-transform:uppercase;letter-spacing:0.1em;">ONLINE</span>
              </div>
            </div>
            <p style="font-size:0.7rem;color:#475569;margin:0 0 14px;font-family:monospace;">35.181.60.162:8080</p>
            <button id="mc-btn-aws" style="
              width:100%;padding:9px;border-radius:8px;border:none;cursor:pointer;
              font-size:0.75rem;font-weight:600;letter-spacing:0.05em;
              background:rgba(239,68,68,0.15);color:#f87171;
              border:1px solid rgba(239,68,68,0.3);
              transition:all 0.2s ease;
            ">
              ⏸ SIMULAR CAÍDA
            </button>
          </div>
        </div>

        <!-- Log de eventos -->
        <div style="background:rgba(0,0,0,0.3);border-radius:8px;padding:12px 16px;">
          <p style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.3em;color:#475569;margin:0 0 8px;">Log de eventos</p>
          <div id="mc-log" style="font-family:monospace;font-size:0.72rem;color:#94a3b8;min-height:40px;max-height:80px;overflow-y:auto;line-height:1.7;">
            <span style="color:#64748b;">Cargando estado de nodos…</span>
          </div>
        </div>
      </div>

      <style<?= isset($cspNonce) ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        @keyframes mc-pulse {
          0%, 100% { opacity: 1; transform: scale(1); }
          50% { opacity: 0.5; transform: scale(0.85); }
        }
        @keyframes mc-blink-red {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.3; }
        }
      </style>

      <script<?= isset($cspNonce) ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
      (function() {
        const nodeState = { gcp: 'online', aws: 'online' };

        function addLog(msg) {
          const log = document.getElementById('mc-log');
          const time = new Date().toLocaleTimeString('es-ES');
          const line = document.createElement('div');
          line.innerHTML = '<span style="color:#475569;">[' + time + ']</span> ' + msg;
          log.prepend(line);
          // Máximo 8 líneas
          while (log.children.length > 8) log.removeChild(log.lastChild);
        }

        function applyNodeUI(node, status) {
          const dot   = document.getElementById('mc-dot-' + node);
          const label = document.getElementById('mc-label-' + node);
          const badge = document.getElementById('mc-badge-' + node);
          const btn   = document.getElementById('mc-btn-' + node);
          const card  = document.getElementById('mc-node-' + node);
          const color = node === 'gcp' ? '#3b82f6' : '#f59e0b';

          if (status === 'online') {
            dot.style.background = '#10b981';
            dot.style.animation = 'mc-pulse 2s infinite';
            label.textContent = 'ONLINE';
            label.style.color = '#10b981';
            badge.style.background = 'rgba(16,185,129,0.15)';
            badge.style.border = '1px solid rgba(16,185,129,0.4)';
            card.style.border = '2px solid ' + color + '66';
            btn.innerHTML = '⏸ SIMULAR CAÍDA';
            btn.style.background = 'rgba(239,68,68,0.15)';
            btn.style.color = '#f87171';
            btn.style.border = '1px solid rgba(239,68,68,0.3)';
          } else {
            dot.style.background = '#ef4444';
            dot.style.animation = 'mc-blink-red 1s infinite';
            label.textContent = 'OFFLINE';
            label.style.color = '#ef4444';
            badge.style.background = 'rgba(239,68,68,0.15)';
            badge.style.border = '1px solid rgba(239,68,68,0.4)';
            card.style.border = '2px solid rgba(239,68,68,0.5)';
            btn.innerHTML = '▶ RECUPERAR NODO';
            btn.style.background = 'rgba(16,185,129,0.15)';
            btn.style.color = '#10b981';
            btn.style.border = '1px solid rgba(16,185,129,0.3)';
          }
          nodeState[node] = status;
        }

        function updateQueueCount(count) {
          const el = document.getElementById('mc-queue-count');
          el.textContent = count;
          el.style.color = count > 0 ? '#f59e0b' : '#10b981';
        }

        // Carga estado inicial
        function fetchStatus() {

          fetch('/api/heatmap/node-control.php')
            .then(r => r.json())
            .then(data => {
              applyNodeUI('gcp', data.nodes.gcp.status);
              applyNodeUI('aws', data.nodes.aws.status);
              updateQueueCount(data.queue_count);
              if (document.getElementById('mc-log').querySelector('span[style*="64748b"]')) {
                addLog('✅ Nodos cargados. GCP: <strong style="color:#10b981">ONLINE</strong> · AWS: <strong style="color:#10b981">ONLINE</strong>');
              }
            })
            .catch(() => addLog('⚠️ No se pudo contactar con la API de control'));
        }

        // Toggle nodo
        window.toggleNode = function(node) {
          const action = nodeState[node] === 'online' ? 'disable' : 'enable';
          const btn = document.getElementById('mc-btn-' + node);
          btn.disabled = true;
          btn.innerHTML = '⏳ Procesando…';

          fetch('/api/heatmap/node-control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ node, action })
          })
          .then(r => r.json())
          .then(data => {
            btn.disabled = false;
            applyNodeUI(node, data.status);
            const nodeLabel = node.toUpperCase();
            if (action === 'disable') {
              addLog('🔴 <strong>' + nodeLabel + '</strong> marcado como OFFLINE. Clicks se encolarán automáticamente.');
            } else {
              addLog('🟢 <strong>' + nodeLabel + '</strong> recuperado. Sincronizando cola pendiente…');
              setTimeout(fetchStatus, 1500); // Actualiza cola tras sync
            }
          })
          .catch(() => {
            btn.disabled = false;
            addLog('❌ Error al cambiar estado del nodo ' + node.toUpperCase());
          });
        };

        // Event listeners para botones (evita onclick inline bloqueado por CSP)
        document.getElementById('mc-btn-gcp').addEventListener('click', function() { toggleNode('gcp'); });
        document.getElementById('mc-btn-aws').addEventListener('click', function() { toggleNode('aws'); });

        // Polling cada 5 segundos
        fetchStatus();
        setInterval(fetchStatus, 5000);
      })();
      </script>

      <div class="heatmap-controls">
        <div class="flex flex-col gap-2">
          <label for="heatmap-page" class="text-xs uppercase tracking-[0.4em] text-slate-400">Página</label>
          <select id="heatmap-page" aria-label="Selecciona una página" disabled>
            <option value="">Cargando…</option>
          </select>
        </div>
        <button id="heatmap-refresh" class="btn btn-primary">Actualizar</button>
      </div>

      <div id="heatmap-status" class="heatmap-status">
        🚀 Recopilando clics desde cada hero-panel, album y sección secreta.
      </div>

      <div class="heatmap-kpis">
        <article class="heatmap-kpi-card">
          <p class="label">Página actual</p>
          <p class="value" id="heatmap-selected-page">—</p>
        </article>
        <article class="heatmap-kpi-card">
          <p class="label">Clicks totales</p>
          <p class="value" id="heatmap-total-clicks">0</p>
        </article>
        <article class="heatmap-kpi-card">
          <p class="label">Intensidad máxima</p>
          <p class="value" id="heatmap-intensity">—</p>
        </article>
      </div>

      <div class="heatmap-canvas-shell">
        <canvas id="heatmap-canvas" role="img" aria-label="Mapa de calor de clics" width="800" height="480"></canvas>
        <div id="heatmap-empty" class="heatmap-empty hidden">
          Todavía no hay clics registrados. Navega la app un poco y vuelve para ver el mapa.
        </div>
      </div>

      <div class="heatmap-legend" aria-hidden="true"></div>
      <section class="heatmap-legend-details" aria-label="Leyenda del mapa de calor">
        <div class="heatmap-legend-details__intro">
          <h2 class="heatmap-legend-details__title">Leyenda del mapa de calor</h2>
          <p class="heatmap-legend-details__description">
            El color azul representa zonas frías con poca interacción. A medida que avanza hacia verde, amarillo, naranja y rojo, aumenta la intensidad de los clics; el rojo marca la máxima concentración dentro de la app.
          </p>
        </div>
        <ul class="heatmap-legend-details__list">
          <li>
            <span class="heatmap-legend-dot heatmap-legend-dot--blue"></span>
            <span>Azul: zona fría, interacción mínima.</span>
          </li>
          <li>
            <span class="heatmap-legend-dot heatmap-legend-dot--green"></span>
            <span>Verde: actividad baja, puntos emergentes.</span>
          </li>
          <li>
            <span class="heatmap-legend-dot heatmap-legend-dot--yellow"></span>
            <span>Amarillo: actividad media, movimiento constante.</span>
          </li>
          <li>
            <span class="heatmap-legend-dot heatmap-legend-dot--orange"></span>
            <span>Naranja: zona caliente con mucho engagement.</span>
          </li>
          <li>
            <span class="heatmap-legend-dot heatmap-legend-dot--red"></span>
            <span>Rojo: punto crítico, máxima concentración de clics.</span>
          </li>
        </ul>
      </section>
      <section class="heatmap-additional space-y-4">
        <div class="heatmap-additional__header">
          <div>
            <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Analítica avanzada</p>
            <h3 class="text-2xl font-bold text-white">Complementa el mapa de calor</h3>
            <p class="text-sm text-slate-300 max-w-2xl">
              Conoce qué zonas verticales concentran más clics y observa cómo evoluciona la interacción a lo largo de toda la altura de la página.
            </p>
          </div>
        </div>
        <div class="heatmap-additional__grid">
          <article class="heatmap-card">
            <header class="heatmap-card__header">
              <p class="text-xs uppercase tracking-[0.5em] text-slate-400">Top / Medio / Bottom</p>
              <h4 class="text-lg text-white">Clicks por zona vertical</h4>
            </header>
            <div class="heatmap-card__body">
              <canvas id="heatmap-bar-zones" role="img" aria-label="Gráfico de barras de zonas"></canvas>
            </div>
          </article>
          <article class="heatmap-card">
            <header class="heatmap-card__header">
              <p class="text-xs uppercase tracking-[0.5em] text-slate-400">Distribución vertical</p>
              <h4 class="text-lg text-white">Clicks por altura</h4>
            </header>
            <div class="heatmap-card__body">
              <canvas id="heatmap-line-distribution" role="img" aria-label="Gráfico de líneas de distribución"></canvas>
            </div>
          </article>
        </div>
      </section>
    </section>
  </div>
</main>
<!-- TODO: añadir hash real de SRI de la versión de Chart.js usada -->
<script
  src="https://cdn.jsdelivr.net/npm/chart.js"
  crossorigin="anonymous"
  <?= isset($cspNonce) ? 'nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
