<?php

declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — Performance';
$activeTopAction = 'performance';
$bodyClass = 'text-gray-200 min-h-screen bg-[#0b0d17] panel-performance-page';

require_once __DIR__ . '/../layouts/header.php';
?>

<header class="app-hero app-hero--tech panel-github__hero">
    <div class="app-hero__inner">
      <div class="space-y-3 max-w-3xl">
      <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
      <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
        Analiza velocidad y cuellos de botella con PageSpeed Insights.
      </p>
      <p class="app-hero__meta text-base text-slate-300">
        Descubre cómo carga cada sección y detecta mejoras para agilizar el sitio.
      </p>
      </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="site-main">
  <div class="max-w-6xl mx-auto py-10 px-4 space-y-8">
    <section class="sonar-panel space-y-8" aria-live="polite">
      <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
          <h2 class="text-3xl text-white sonar-hero-title">Panel de rendimiento</h2>
        </div>
        <div id="performance-state" class="sonar-alert" role="status" aria-live="polite" aria-atomic="true"></div>
        <div id="performance-result" class="space-y-6" aria-live="polite"></div>
        <div class="flex justify-end">
          <button id="performance-refresh-button" class="btn btn-primary inline-flex items-center gap-2 mt-4" type="button">
            Actualizar análisis
          </button>
        </div>
      </div>
    </section>
  </div>
</main>

<?php
$scripts = ['/assets/js/panel-performance.js'];
require_once __DIR__ . '/../layouts/footer.php';
