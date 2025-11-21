<?php
declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — Heatmap';
$additionalStyles = ['/assets/css/seccion.css', '/assets/css/heatmap.css'];
$activeTopAction = 'secret';
$scripts = ['/assets/js/heatmap-viewer.js'];

require_once __DIR__ . '/../layouts/header.php';
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
    <section class="heatmap-panel">
      <div class="heatmap-controls">
        <div class="flex flex-col gap-2">
          <label for="heatmap-page" class="text-xs uppercase tracking-[0.4em] text-slate-400">Página</label>
          <select id="heatmap-page" aria-label="Selecciona una página" disabled>
            <option value="">Cargando…</option>
          </select>
        </div>
        <button id="heatmap-refresh" class="btn btn-primary">Actualizar heatmap</button>
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
  integrity="TODO_ADD_REAL_HASH_HERE"
  crossorigin="anonymous"
></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
