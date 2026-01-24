<?php

declare(strict_types=1);

use App\Infrastructure\Http\AuthGuards;

AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$pageTitle = 'Clean Marvel Album — Snyk Code Audit';
$additionalStyles = ['/assets/css/snyk.css', '/assets/css/sonar.css'];
$activeTopAction = 'secret';

require_once __DIR__ . '/../layouts/header.php';
?>

<header class="app-hero app-hero--tech">
  <div class="app-hero__inner">
    <div class="space-y-3 max-w-3xl">
      <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
      <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
        Escaneo automático de vulnerabilidades con Snyk API.
      </p>
      <p class="app-hero__meta text-base text-slate-300">
        Analiza dependencias y código en busca de vulnerabilidades conocidas usando Snyk.
      </p>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="site-main">
  <div class="max-w-6xl mx-auto py-10 px-4">
    <section class="sonar-panel space-y-8" aria-live="polite">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div class="space-y-2">
          <h2 class="sonar-hero-title text-4xl text-white">Snyk Code Audit</h2>
          <p class="text-slate-300 text-sm">Organización: <span class="font-semibold text-white" id="snyk-org">20luisma</span></p>
          <p class="text-slate-300 text-sm">Último escaneo: <span class="font-semibold text-white" id="snyk-last-scan">—</span></p>
        </div>
        <div class="flex flex-col items-center gap-4 text-center">
          <button id="snyk-refresh-button" class="btn btn-primary inline-flex items-center gap-2 mx-auto">
            <span>Actualizar</span>
          </button>
        </div>
      </div>

      <div id="snyk-error" class="sonar-alert" role="alert" aria-live="assertive" aria-atomic="true"></div>

      <!-- Loader moderno azul -->
      <div id="snyk-loader" class="panel-loader hidden" role="status" aria-live="polite" aria-atomic="true">
        <div class="panel-loader__dots" aria-hidden="true">
          <span></span>
          <span></span>
          <span></span>
        </div>
        <span class="panel-loader__text">Escaneando vulnerabilidades...</span>
      </div>

      <div class="sonar-grid metrics" id="snyk-metrics">
        <article class="sonar-card">
          <h4>Total Vulnerabilidades</h4>
          <p class="sonar-card-value text-red-300" id="metric-total">—</p>
          <p class="sonar-card-sub">Todas las severidades.</p>
        </article>
        <article class="sonar-card">
          <h4>Alta Severidad</h4>
          <p class="sonar-card-value text-red-400" id="metric-high">—</p>
          <p class="sonar-card-sub">Críticas y urgentes.</p>
        </article>
        <article class="sonar-card">
          <h4>Media Severidad</h4>
          <p class="sonar-card-value text-amber-300" id="metric-medium">—</p>
          <p class="sonar-card-sub">Requieren atención.</p>
        </article>
        <article class="sonar-card">
          <h4>Baja Severidad</h4>
          <p class="sonar-card-value text-yellow-200" id="metric-low">—</p>
          <p class="sonar-card-sub">Menor prioridad.</p>
        </article>
        <article class="sonar-card">
          <h4>Proyecto Escaneado</h4>
          <p class="sonar-card-value text-sky-200" id="metric-project">—</p>
          <p class="sonar-card-sub">Repositorio analizado.</p>
        </article>
      </div>

      <!-- Explicación de Snyk -->
      <div class="mt-8 pt-6 border-t border-slate-700/50">
        <h3 class="text-lg font-semibold text-white mb-3">¿Qué estamos analizando?</h3>
        <div class="space-y-4 text-sm text-slate-300 leading-relaxed">
          <div>
            <p class="text-cyan-400 font-semibold mb-1">🔍 Dependencias</p>
            <p class="text-slate-400">
              Snyk escanea todas las dependencias del proyecto (npm, composer, etc.) en busca de vulnerabilidades conocidas en bases de datos públicas.
            </p>
          </div>
          <div>
            <p class="text-cyan-400 font-semibold mb-1">🛡️ Código Fuente</p>
            <p class="text-slate-400">
              Analiza el código fuente en busca de patrones inseguros, inyecciones SQL, XSS y otras vulnerabilidades comunes.
            </p>
          </div>
          <p class="text-xs text-slate-500 mt-4">
            Los datos se obtienen directamente de la API de Snyk para la organización <code class="text-cyan-400 bg-slate-900 px-1 rounded">20luisma</code>. 
            Resultados actualizados en tiempo real con el botón "Re-escanear ahora".
          </p>
        </div>
      </div>
    </section>
  </div>
</main>

<!-- FOOTER -->
<footer class="site-footer">
  <small>© creawebes 2025-2026 · Clean Marvel Album</small>
</footer>

<?php
$scripts = ['/assets/js/snyk.js'];
require_once __DIR__ . '/../layouts/footer.php';
?>
