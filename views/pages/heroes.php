<?php

declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — Héroes';
$additionalStyles = ['/assets/css/heroes.css'];
require __DIR__ . '/../layouts/header.php';
?>

  <!-- HERO / HEADER -->
  <header class="app-hero">
    <div class="app-hero__inner">
      <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div class="space-y-3 max-w-3xl">
          <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
          <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
            Explora la arquitectura limpia inspirada en el universo Marvel.
          </p>
        </div>
      </div>
      <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
        <p id="album-meta" class="app-hero__meta flex-1 min-w-[14rem]"></p>
        <?php require __DIR__ . '/../partials/top-actions.php'; ?>
      </div>
    </div>
  </header>

  <main class="site-main">
    <div class="max-w-7xl mx-auto py-8 px-4 space-y-8">
      <div id="hero-focus-backdrop" class="hero-focus-backdrop hidden"></div>
      <!-- GRID: Aside a la izquierda, Main a la derecha -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- ASIDE IZQUIERDA: Añadir Héroe + Actividad -->
        <aside class="lg:col-span-1 self-start space-y-6">
        <section class="card section-lined rounded-2xl p-6 shadow-xl">
          <h2 class="text-3xl text-white mb-4">Añadir Héroe</h2>
          <form id="hero-form" class="space-y-4">
            <div class="grid grid-cols-1 gap-4">
              <input id="hero-name" class="w-full px-4 py-3 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white" type="text" placeholder="Nombre del Héroe" required>
              <input id="hero-image" class="w-full px-4 py-3 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white" type="url" placeholder="URL de la Imagen" required>
            </div>
            <textarea id="hero-content" class="w-full px-4 py-3 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white resize-none min-h-24" rows="3" placeholder="Biografía del Héroe"></textarea>
            <button class="btn btn-primary w-full" type="submit">¡Añadir Héroe!</button>
          </form>
          <p id="hero-message" class="text-sm mt-4 hidden msg-hidden"></p>
        </section>

        <!-- AVISADOR DE ACTIVIDAD: solo la más reciente, con navegación -->
        <section class="card section-lined rounded-2xl p-6 shadow-xl">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h2 class="text-3xl text-white">🔔 Actividad</h2>
            <div class="flex items-center gap-2">
              <button id="activity-prev" class="btn btn-secondary text-xs">⟵ Anterior</button>
              <button id="activity-next" class="btn btn-secondary text-xs">Siguiente ⟶</button>
              <button id="clear-hero-activity" class="btn btn-danger text-xs">Limpiar</button>
            </div>
          </div>

          <div id="hero-activity-empty" class="bg-slate-900/70 border border-slate-700 rounded-xl p-4 text-sm text-gray-300 italic">
            No hay actividad registrada.
          </div>

          <!-- Vista de 1 sola entrada -->
          <div id="hero-activity-view" class="hidden bg-slate-900/80 border border-slate-700 rounded-xl p-4 space-y-2">
            <div class="flex items-center justify-between">
              <span id="hero-activity-tag" class="inline-flex items-center px-2 py-1 rounded-md text-[0.65rem] font-black uppercase tracking-[0.18em] border">
                —
              </span>
              <div class="flex items-center gap-2 text-xs text-gray-400">
                <time id="hero-activity-date" class="font-mono text-amber-300/80">—</time>
                <span id="hero-activity-counter">0/0</span>
              </div>
            </div>
            <p id="hero-activity-title" class="text-sm text-gray-100 leading-tight">—</p>
          </div>
        </section>
      </aside>

      <!-- MAIN DERECHA: Galería -->
      <section class="lg:col-span-2 space-y-8">
        <!-- Línea roja arriba -->
        <section class="card section-lined rounded-2xl p-6 shadow-xl">
          <!-- Encabezado de galería con contador + filtros + orden -->
          <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-3">
              <h2 class="text-3xl text-white">Galería de Héroes</h2>
              <span id="heroes-counter" class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-bold bg-slate-700 text-gray-100 border border-slate-600">
                0 héroes
              </span>
            </div>
            <div id="heroes-controls" class="grid grid-cols-1 sm:grid-cols-3 gap-2 w-full sm:w-auto">
              <div class="sm:col-span-2">
                <label class="block text-xs text-gray-400 mb-1">Buscar (nombre o biografía)</label>
                <input id="filter-q" type="search" placeholder="Ej: spider, wakanda, mutante..."
                  class="w-full px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white" />
              </div>
              <div>
                <label class="block text-xs text-gray-400 mb-1">Ordenar</label>
                <select id="filter-order"
                  class="w-full px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white">
                  <option value="az">A → Z</option>
                  <option value="za">Z → A</option>
                  <option value="recent">Recientes</option>
                </select>
              </div>
            </div>
          </div>

          <div id="heroes-grid" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"></div>
        </section>
      </section>
      </div>
    </div>
  </main>

<?php
$scripts = ['/assets/js/heroes.js'];
require __DIR__ . '/../layouts/footer.php';
