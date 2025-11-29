<?php

declare(strict_types=1);

use App\Infrastructure\Http\AuthGuards;

AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$pageTitle = 'Clean Marvel Album — Repo Marvel';
$additionalStyles = ['/assets/css/sonar.css'];
$activeTopAction = 'repo-marvel';
$bodyClass = 'text-gray-200 min-h-screen bg-[#0b0d17] panel-github-page';

require_once __DIR__ . '/../layouts/header.php';
?>

<header class="app-hero app-hero--tech panel-github__hero">
  <div class="app-hero__inner">
    <div class="space-y-3 max-w-3xl">
      <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
      <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
        Explora la estructura del proyecto Clean Marvel Album directamente desde GitHub.
      </p>
      <p class="app-hero__meta text-base text-slate-300">
        Navega carpetas y archivos, y abre cualquier archivo en GitHub sin salir del dashboard.
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
      <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 class="sonar-hero-title text-3xl text-white">Clean Marvel Album</h2>
          <p class="text-slate-300 text-sm">Contenido sincronizado en tiempo real con GitHub.</p>
        </div>
      </div>

      <nav id="repo-browser-breadcrumb" class="repo-browser__breadcrumb flex flex-wrap items-center gap-2 text-xs text-slate-400 tracking-[0.3em] uppercase">
        <span>Raíz</span>
      </nav>
      <div id="repo-browser-state" class="text-sm text-slate-400">Cargando contenido del repo…</div>
      <div id="repo-browser-result"></div>
    </section>
  </div>
</main>

<?php
$scripts = ['/assets/js/panel-repo-marvel.js'];
require_once __DIR__ . '/../layouts/footer.php';
