<?php
declare(strict_types=1);
use App\Infrastructure\Http\AuthGuards;

AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$activeTopAction = 'sonar';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Panel de Calidad – SonarCloud</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Roboto:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/main.css" />
  <link rel="stylesheet" href="/assets/css/sonar.css" />
</head>

<body class="text-gray-200 min-h-screen bg-[#0b0d17]">

  <!-- HERO / HEADER -->
  <header class="app-hero app-hero--tech">
    <div class="app-hero__inner">
      <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div class="space-y-3 max-w-3xl">
          <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
          <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
            Consulta en tiempo real las métricas clave del repositorio Clean Marvel.
          </p>
          <p class="app-hero__meta text-base text-slate-300">
            Monitoreamos bugs, vulnerabilidades, code smells y cobertura usando la API oficial de SonarCloud.
          </p>
        </div>
      </div>
      <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
        <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
      </div>
    </div>
  </header>

  <main id="main-content" tabindex="-1" role="main" class="site-main">
    <div class="max-w-6xl mx-auto py-10 px-4">
      <section class="sonar-panel section-lined space-y-10" aria-live="polite">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <div class="space-y-2">
            <h2 class="sonar-hero-title text-4xl text-white" id="sonar-project-name">Marvel Quality Board</h2>
            <p class="text-slate-300 text-sm">Project Key: <span class="font-semibold text-white" id="sonar-project-key">—</span></p>
            <p class="text-slate-300 text-sm">Último análisis: <span class="font-semibold text-white" id="sonar-updated-at">—</span></p>
          </div>
          <div class="flex flex-col items-center gap-4 text-center">
            <span id="sonar-status-pill" class="sonar-status-pill" data-level="alert">Esperando datos</span>
            <div class="flex flex-wrap justify-center gap-3">
              <button id="sonar-refresh-button" class="btn btn-primary inline-flex items-center gap-2 mx-auto">
                <span>Actualizar</span>
              </button>
            </div>
            <!-- Indicador de sincronización: reemplaza el texto por 3 bolitas centradas y fijas bajo el botón -->
            <div class="h-6 flex items-center justify-center gap-3" aria-hidden="true">
              <span class="sonar-sync-dot w-2.5 h-2.5 rounded-full transition-all duration-200"></span>
              <span class="sonar-sync-dot w-2.5 h-2.5 rounded-full transition-all duration-200"></span>
              <span class="sonar-sync-dot w-2.5 h-2.5 rounded-full transition-all duration-200"></span>
            </div>
          </div>
        </div>

        <div id="sonar-error" class="sonar-alert" role="alert" aria-live="assertive" aria-atomic="true"></div>

        <div class="sonar-grid metrics">
          <article class="sonar-card">
            <h4>Lines of Code</h4>
            <p class="sonar-card-value" id="metric-ncloc">—</p>
            <p class="sonar-card-sub">Código total analizado.</p>
          </article>
          <article class="sonar-card">
            <h4>Code Smells</h4>
            <p class="sonar-card-value text-rose-300" id="metric-smells">—</p>
            <p class="sonar-card-sub">Posibles problemas de mantenimiento.</p>
          </article>
          <article class="sonar-card">
            <h4>Bugs</h4>
            <p class="sonar-card-value text-red-300" id="metric-bugs">—</p>
            <p class="sonar-card-sub">Errores críticos reportados.</p>
          </article>
          <article class="sonar-card">
            <h4>Vulnerabilidades</h4>
            <p class="sonar-card-value text-red-200" id="metric-vulns">—</p>
            <p class="sonar-card-sub">Riesgos de seguridad detectados.</p>
          </article>
          <article class="sonar-card">
            <h4>Duplicated Code</h4>
            <p class="sonar-card-value text-amber-200" id="metric-dup">—</p>
            <p class="sonar-card-sub">Porcentaje de duplicación.</p>
          </article>
          <article class="sonar-card">
            <h4>Complexity</h4>
            <p class="sonar-card-value text-sky-200" id="metric-complexity">—</p>
            <p class="sonar-card-sub">Complejidad ciclomatica acumulada.</p>
          </article>
        </div>

        <div class="sonar-grid graphs">
          <article class="sonar-card sonar-graph">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Marvel Quality Score</p>
            <p class="sonar-card-value text-white" id="sonar-quality-score">—</p>
            <canvas id="sonar-score-chart"></canvas>
          </article>
          <article class="sonar-card sonar-graph">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Alertas principales</p>
            <canvas id="sonar-alerts-chart"></canvas>
          </article>
          <article class="sonar-card sonar-graph">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Cobertura</p>
            <canvas id="sonar-coverage-chart"></canvas>
            <p id="sonar-coverage-warning" class="text-amber-200 hidden">Cobertura no disponible todavía.</p>
          </article>
        </div>

        <!-- Coverage transparency note -->
        <div class="mt-6 p-4 bg-slate-800/30 rounded-lg border border-slate-700/50">
          <p class="text-sm text-slate-300 leading-relaxed">
            <span class="text-cyan-400 font-bold text-base">*</span> <span class="text-white font-semibold">Cobertura de backend PHP</span> (directorio <code class="text-cyan-400 bg-slate-900 px-2 py-0.5 rounded">src/</code>).
            Frontend (<code class="text-slate-500 bg-slate-900 px-2 py-0.5 rounded">public/</code>, <code class="text-slate-500 bg-slate-900 px-2 py-0.5 rounded">views/</code>) se testea con herramientas especializadas (Jest, Cypress).
            <br>
            <span class="text-slate-400 text-xs mt-1 inline-block">Siguiendo estándares de Laravel/Symfony. Ver <a href="/readme" class="text-cyan-400 hover:underline font-medium">COVERAGE.md</a> para detalles completos.</span>
          </p>
        </div>
      </section>
    </div>
  </main>


  <!-- FOOTER -->
  <footer class="site-footer">
    <small>© creawebes 2025 · Clean Marvel Album</small>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="/assets/js/sonar.js" defer></script>
</body>

</html>
