<?php

declare(strict_types=1);

use App\Infrastructure\Http\AuthGuards;

AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$pageTitle = 'Clean Marvel Album — Accesibilidad';
$activeTopAction = 'accessibility';
$bodyClass = 'text-gray-200 min-h-screen bg-[#0b0d17] panel-accessibility-page';
$additionalStyles = ['/assets/css/panel-accessibility.css'];

require_once __DIR__ . '/../layouts/header.php';
?>

<header class="app-hero app-hero--tech panel-accessibility__hero">
  <div class="app-hero__inner">
    <div class="space-y-3 max-w-3xl">
      <h1 class="app-hero__title text-4xl sm:text-5xl">
        Clean Architecture with Marvel
      </h1>
      <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
        Analiza las páginas clave y detecta errores, contrastes y alertas con WAVE.
      </p>
      <p class="app-hero__meta text-sm text-slate-300">
        Datos e informes directos vía WAVE, integrados en el panel Marvel.
      </p>
    </div>

    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="site-main panel-accessibility">
  <div class="max-w-6xl mx-auto py-10 px-4">
    <section class="sonar-panel space-y-8 rounded-3xl border border-slate-700/60 bg-[#050814] px-6 py-7 shadow-xl shadow-black/40" aria-live="polite">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="space-y-1">
          <h2 class="text-2xl font-semibold text-white">
            Panel de resultados de accesibilidad
          </h2>
          <p class="text-sm text-slate-300 max-w-xl">
            Pulsa Analizar accesibilidad para ver el análisis completo.
          </p>

        </div>

        <div class="flex flex-col gap-3 items-center text-center">
          <button id="btn-accessibility-run" class="btn btn-primary inline-flex items-center gap-2">
            Analizar accesibilidad
          </button>
          <p class="text-[0.65rem] uppercase tracking-[0.3em] text-slate-500">
            Protegido por WAVE API Key
          </p>
        </div>
      </div>

      <!-- Aquí el JS inyecta el resumen y la tabla -->
      <div id="accessibility-result" class="space-y-4"></div>
    </section>
  </div>
</main>

<?php
$scripts = ['/assets/js/panel-accessibility.js'];
require_once __DIR__ . '/../layouts/footer.php';
