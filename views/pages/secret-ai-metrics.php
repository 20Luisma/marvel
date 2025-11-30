<?php

declare(strict_types=1);

use App\Infrastructure\Http\AuthGuards;

AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$pageTitle = 'Clean Marvel Album — AI Token & Cost Dashboard';
$additionalStyles = ['/assets/css/ai-metrics.css', '/assets/css/sonar.css'];
$activeTopAction = 'secret';

require_once __DIR__ . '/../layouts/header.php';
?>

<header class="app-hero app-hero--tech">
  <div class="app-hero__inner">
    <div class="space-y-3 max-w-3xl">
      <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
      <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
        Panel completo de consumo de IA: tokens, llamadas, coste y latencia del Marvel Agent.
      </p>
      <p class="app-hero__meta text-base text-slate-300">
        Monitoreo en tiempo real de uso de OpenAI API con métricas detalladas.
      </p>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="site-main">
  <div class="max-w-7xl mx-auto py-10 px-4">
    <section class="sonar-panel space-y-8" aria-live="polite">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div class="space-y-2">
          <h2 class="sonar-hero-title text-4xl text-white">Marvel Agent Metrics</h2>
          <p class="text-slate-300 text-sm">Dashboard de tokens y costes de IA</p>
        </div>
        <div class="flex flex-col items-center gap-4 text-center">
          <button id="metrics-refresh-button" class="btn btn-primary inline-flex items-center gap-2 mx-auto">
            <span>Actualizar</span>
          </button>
        </div>
      </div>

      <div id="metrics-error" class="sonar-alert hidden" role="alert" aria-live="assertive" aria-atomic="true"></div>

      <!-- Loader -->
      <div id="metrics-loader" class="panel-loader hidden" role="status" aria-live="polite" aria-atomic="true">
        <div class="panel-loader__dots" aria-hidden="true">
          <span></span>
          <span></span>
          <span></span>
        </div>
        <span class="panel-loader__text">Cargando métricas...</span>
      </div>

      <!-- SECCIÓN A: RESUMEN GLOBAL -->
      <div id="global-metrics" class="space-y-6">
        <h3 class="text-2xl font-semibold text-white mb-4">📊 Resumen Global</h3>
        <div class="sonar-grid metrics">
          <article class="sonar-card">
            <h4>Total Llamadas</h4>
            <p class="sonar-card-value text-cyan-300" id="metric-total-calls">—</p>
            <p class="sonar-card-sub">Todas las llamadas a la API</p>
          </article>
          <article class="sonar-card">
            <h4>Total Tokens</h4>
            <p class="sonar-card-value text-blue-300" id="metric-total-tokens">—</p>
            <p class="sonar-card-sub">Prompt + Completion</p>
          </article>
          <article class="sonar-card">
            <h4>Tokens Hoy</h4>
            <p class="sonar-card-value text-green-300" id="metric-tokens-today">—</p>
            <p class="sonar-card-sub">Uso del día actual</p>
          </article>
          <article class="sonar-card">
            <h4>Tokens 7 Días</h4>
            <p class="sonar-card-value text-purple-300" id="metric-tokens-7days">—</p>
            <p class="sonar-card-sub">Última semana</p>
          </article>
          <article class="sonar-card">
            <h4>Promedio/Llamada</h4>
            <p class="sonar-card-value text-amber-300" id="metric-avg-tokens">—</p>
            <p class="sonar-card-sub">Tokens por llamada</p>
          </article>
          <article class="sonar-card">
            <h4>Latencia Media</h4>
            <p class="sonar-card-value text-sky-300" id="metric-avg-latency">—</p>
            <p class="sonar-card-sub">Milisegundos</p>
          </article>
          <article class="sonar-card">
            <h4>Llamadas Fallidas</h4>
            <p class="sonar-card-value text-red-300" id="metric-failed-calls">—</p>
            <p class="sonar-card-sub">Errores detectados</p>
          </article>
          <article class="sonar-card" id="cost-card" style="display: none;">
            <h4>Coste Total (USD)</h4>
            <p class="sonar-card-value text-yellow-300" id="metric-cost-total">—</p>
            <p class="sonar-card-sub">Estimado USD</p>
          </article>
          <article class="sonar-card" id="cost-eur-card" style="display: none;">
            <h4>Coste Total (EUR)</h4>
            <p class="sonar-card-value text-yellow-400" id="metric-cost-total-eur">—</p>
            <p class="sonar-card-sub">Estimado EUR</p>
          </article>
        </div>
      </div>

      <!-- SECCIÓN B: DESGLOSE POR MODELO -->
      <div id="by-model-section" class="space-y-4">
        <h3 class="text-2xl font-semibold text-white mb-4">🤖 Desglose por Modelo</h3>
        <div id="by-model-container" class="space-y-3"></div>
      </div>

      <!-- SECCIÓN C: DESGLOSE POR FEATURE -->
      <div id="by-feature-section" class="space-y-4">
        <h3 class="text-2xl font-semibold text-white mb-4">⚡ Desglose por Feature</h3>
        <div id="by-feature-container" class="space-y-3"></div>
      </div>

      <!-- SECCIÓN D: ÚLTIMAS LLAMADAS -->
      <div id="recent-calls-section" class="space-y-4">
        <h3 class="text-2xl font-semibold text-white mb-4">🕒 Últimas 10 Llamadas</h3>
        <div class="overflow-x-auto">
          <table id="recent-calls-table" class="w-full text-sm text-left">
            <thead class="text-xs uppercase bg-slate-800 text-slate-300">
              <tr>
                <th class="px-4 py-3">Fecha</th>
                <th class="px-4 py-3">Feature</th>
                <th class="px-4 py-3">Modelo</th>
                <th class="px-4 py-3">Tokens</th>
                <th class="px-4 py-3">Latencia</th>
                <th class="px-4 py-3">Estado</th>
              </tr>
            </thead>
            <tbody id="recent-calls-body" class="divide-y divide-slate-700">
              <!-- Populated by JS -->
            </tbody>
          </table>
        </div>
      </div>

      <!-- Explicación -->
      <div class="mt-8 pt-6 border-t border-slate-700/50">
        <h3 class="text-lg font-semibold text-white mb-3">ℹ️ Información</h3>
        <div class="space-y-4 text-sm text-slate-300 leading-relaxed">
          <p>
            Este dashboard monitorea en tiempo real el uso de la API de OpenAI por parte del Marvel Agent.
            Los datos se registran automáticamente en cada llamada y se almacenan en formato JSONL.
          </p>
          <p class="text-xs text-slate-500">
            Las métricas se actualizan automáticamente. Los costes son estimados basados en las tarifas configuradas en .env.
          </p>
        </div>
      </div>
    </section>
  </div>
</main>



<?php
$scripts = ['/assets/js/ai-metrics.js'];
require_once __DIR__ . '/../layouts/footer.php';
?>
