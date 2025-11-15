<?php

declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — Oficial Marvel';
$additionalStyles = [];
$activeTopAction = 'official';

require_once __DIR__ . '/../layouts/header.php';
?>

<!-- HERO / HEADER -->
<header class="app-hero">
  <div class="app-hero__inner">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="space-y-3 max-w-3xl">
        <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
        <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl hero-text-nowrap">
          Curamos contenidos oficiales de Marvel y los integramos al flujo limpio de la app.
        </p>
        <p class="app-hero__meta text-base text-slate-300">
          Esta sección recibe datos dinámicos desde n8n + pipelines de scraping.
        </p>
      </div>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main class="site-main">
  <div class="max-w-5xl mx-auto py-8 px-4 space-y-8">
    <section class="card section-lined rounded-2xl p-6 shadow-xl space-y-6" id="marvel-dynamic-section" aria-live="polite">
      <header class="space-y-2">
        <p class="text-xs uppercase tracking-[0.28em] text-gray-400">Oficial Marvel</p>
        <h2 class="text-3xl text-white">Último video oficial (n8n → backend)</h2>
        <p class="text-sm text-slate-400">Este bloque se rellena con el JSON guardado en /api/ultimo-video-marvel.json</p>
      </header>
      <div id="marvel-loading" class="flex flex-col items-center gap-3 py-6 text-slate-300">
        <div class="w-9 h-9 border-4 border-slate-700 border-t-blue-400 rounded-full animate-spin"></div>
        <p>Sincronizando con Marvel Entertainment…</p>
      </div>
    </section>
  </div>
</main>

<script src="/assets/js/oficial-marvel.js"></script>

<?php
$scripts = [];
require_once __DIR__ . '/../layouts/footer.php';
